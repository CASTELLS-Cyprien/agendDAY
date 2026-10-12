<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\NotificationModel;
use App\Models\ReminderModel;
use DateTimeImmutable;

/**
 * Moteur des rappels, exécuté périodiquement par cron/send_reminders.php.
 *
 * Étape 1 — échéances : chaque rappel arrivé à échéance produit UNE notification
 *   in-app (clé unique par occurrence → idempotent même si deux exécutions se
 *   chevauchent), puis est reprogrammé sur l'occurrence suivante.
 * Étape 2 — e-mails : les notifications dont l'e-mail est en attente sont envoyées,
 *   avec nouvelles tentatives espacées en cas d'échec SMTP et un nombre d'essais borné.
 *
 * Un rappel en retard (cron interrompu) est encore délivré tant que l'événement
 * n'a pas commencé depuis plus de ReminderScheduler::LATE_GRACE_MINUTES.
 */
final class ReminderService
{
    private const REMINDER_BATCH = 200;
    private const EMAIL_BATCH    = 50;

    /** Délai avant la tentative n+1, en minutes (indexé par le nombre d'essais déjà faits). */
    private const RETRY_DELAYS = [1 => 5, 2 => 15, 3 => 60];

    /** Au-delà, les e-mails restants sont laissés à l'exécution suivante. */
    private const TIME_BUDGET_SECONDS = 25.0;

    private ReminderModel $reminders;
    private NotificationModel $notifications;
    private ReminderMailer $mailer;

    public function __construct(
        ?ReminderModel $reminders = null,
        ?NotificationModel $notifications = null,
        ?ReminderMailer $mailer = null
    ) {
        $this->reminders     = $reminders ?? new ReminderModel();
        $this->notifications = $notifications ?? new NotificationModel();
        $this->mailer        = $mailer ?? new MailService();
    }

    /**
     * @return array<string, int> compteurs de l'exécution
     */
    public function run(DateTimeImmutable $now): array
    {
        $stats = [
            'notifications'    => 0,
            'expired'          => 0,
            'emails_sent'      => 0,
            'emails_retry'     => 0,
            'emails_failed'    => 0,
            'emails_cancelled' => 0,
            'emails_released'  => $this->notifications->releaseStaleEmails($now),
        ];

        $this->processDueReminders($now, $stats);
        $this->sendPendingEmails($now, $stats);

        return $stats;
    }

    /**
     * Simulation sans aucune écriture (option --dry-run).
     *
     * @return array<string, int>
     */
    public function preview(DateTimeImmutable $now): array
    {
        return [
            'due_reminders'  => $this->reminders->countDue($now),
            'pending_emails' => $this->notifications->countPendingEmails($now),
        ];
    }

    private function processDueReminders(DateTimeImmutable $now, array &$stats): void
    {
        foreach ($this->reminders->due($now, self::REMINDER_BATCH) as $due) {
            $this->reminders->transaction(function () use ($due, $now, &$stats): void {
                $row = $this->reminders->lockForProcessing((int) $due['id']);

                // Traité par une autre exécution, ou reprogrammé entre-temps (événement modifié)
                if ($row === null || $row['remind_at'] !== $due['remind_at'] || $row['occurrence_at'] === null) {
                    return;
                }

                $occurrence = new DateTimeImmutable($row['occurrence_at']);
                $date       = $occurrence->format('Y-m-d');
                $offset     = (int) $row['offset_minutes'];

                if ($now < ReminderScheduler::expiry($row, $date)) {
                    $emailStatus = ((int) $row['email_reminders'] === 1 && (int) $row['is_confirmed'] === 1)
                        ? 'pending'
                        : 'disabled';

                    $created = $this->notifications->createReminder(
                        (int) $row['userID'],
                        (int) $row['event_id'],
                        $offset,
                        $occurrence,
                        !empty($row['all_day']),
                        (string) $row['title'],
                        $emailStatus,
                        $now
                    );
                    if ($created) {
                        $stats['notifications']++;
                    }
                } else {
                    $stats['expired']++;
                }

                $next = ReminderScheduler::next($row, $offset, $date, $now);
                $this->reminders->reschedule((int) $row['id'], $next['occurrence'] ?? null, $next['remindAt'] ?? null);
            });
        }
    }

    private function sendPendingEmails(DateTimeImmutable $now, array &$stats): void
    {
        $startedAt = microtime(true);

        foreach ($this->notifications->emailQueue($now, self::EMAIL_BATCH) as $item) {
            if (microtime(true) - $startedAt > self::TIME_BUDGET_SECONDS) {
                break;
            }
            $id = (int) $item['id'];
            if (!$this->notifications->claimEmail($id, $now)) {
                continue; // réservé par une exécution concurrente
            }

            $attempts   = (int) $item['email_attempts'] + 1;
            $occurrence = new DateTimeImmutable((string) $item['occurrence_at']);
            $date       = $occurrence->format('Y-m-d');

            // L'état a pu changer depuis la création de la notification : on revérifie.
            if ($item['current_event_id'] === null) {
                $this->notifications->finishEmail($id, 'cancelled', 'Événement supprimé avant l\'envoi.');
                $stats['emails_cancelled']++;
                continue;
            }
            if ((int) $item['email_reminders'] !== 1 || (int) $item['is_confirmed'] !== 1) {
                $this->notifications->finishEmail($id, 'disabled', 'Rappels par e-mail désactivés.');
                $stats['emails_cancelled']++;
                continue;
            }
            if (!$this->isStillScheduled($item, $date, $occurrence)) {
                $this->notifications->finishEmail($id, 'cancelled', 'Événement déplacé avant l\'envoi.');
                $stats['emails_cancelled']++;
                continue;
            }
            if ($now >= ReminderScheduler::expiry($item, $date)) {
                $this->notifications->finishEmail($id, 'skipped', 'Trop tard : l\'événement a déjà commencé.');
                $stats['emails_cancelled']++;
                continue;
            }

            try {
                $event       = $item;
                $event['id'] = (int) $item['current_event_id'];
                $this->mailer->sendReminder(
                    (string) $item['email'],
                    (string) $item['nomUtilisateur'],
                    $event,
                    $occurrence,
                    DateText::relative($occurrence, !empty($item['all_day']), $now)
                );
                $this->notifications->finishEmail($id, 'sent', null, null, $now);
                $stats['emails_sent']++;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                error_log('[rappels] Échec e-mail notification #' . $id . ' (essai ' . $attempts . ') : ' . $error);

                if ($attempts >= NotificationModel::EMAIL_MAX_ATTEMPTS) {
                    $this->notifications->finishEmail($id, 'failed', $error);
                    $stats['emails_failed']++;
                } else {
                    $delay = self::RETRY_DELAYS[$attempts] ?? 60;
                    $this->notifications->finishEmail($id, 'pending', $error, $now->modify("+{$delay} minutes"));
                    $stats['emails_retry']++;
                }
            }
        }
    }

    /**
     * L'occurrence visée par la notification existe-t-elle toujours, à la même heure ?
     */
    private function isStillScheduled(array $event, string $date, DateTimeImmutable $occurrence): bool
    {
        return Recurrence::isOccurrence(
            (string) $event['dateEvent'],
            (string) $event['recurrence'],
            $event['recurrence_until'] ?: null,
            $date
        ) && ReminderScheduler::referenceTime($event, $date) == $occurrence;
    }
}
