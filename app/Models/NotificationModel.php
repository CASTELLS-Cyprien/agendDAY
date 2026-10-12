<?php
declare(strict_types=1);
namespace App\Models;

use DateTimeImmutable;

/**
 * Notifications in-app et suivi de l'envoi des e-mails de rappel associés.
 *
 * Cycle de vie de email_status :
 *   pending ──(réservé par le cron)──► sending ──► sent       (accepté par le serveur SMTP)
 *      ▲                                  │    ──► pending    (échec, nouvel essai plus tard)
 *      └──────────(verrou expiré)─────────┘    ──► failed     (tentatives épuisées)
 *   + disabled (e-mails désactivés), cancelled (événement supprimé/déplacé),
 *     skipped (trop tard), none (notification sans e-mail).
 */
class NotificationModel extends BaseModel
{
    public const EMAIL_MAX_ATTEMPTS = 4;

    /** Un envoi resté « sending » plus longtemps est considéré comme interrompu. */
    private const EMAIL_LOCK_MINUTES = 15;

    private const DATETIME = 'Y-m-d H:i:s';

    /**
     * Crée la notification d'un rappel. La clé unique (event_id, offset_minutes,
     * occurrence_at) rend l'opération idempotente : retourne false si elle existe déjà.
     */
    public function createReminder(
        int $userId,
        int $eventId,
        int $offset,
        DateTimeImmutable $occurrence,
        bool $allDay,
        string $title,
        string $emailStatus,
        DateTimeImmutable $now
    ): bool {
        $stmt = $this->db()->prepare(
            "INSERT INTO notifications
                (user_id, event_id, type, offset_minutes, occurrence_at, all_day, title,
                 email_status, email_next_attempt_at, created_at)
             VALUES
                (:userId, :eventId, 'reminder', :offset, :occurrence, :allDay, :title,
                 :emailStatus, :nextAttempt, :now)
             ON DUPLICATE KEY UPDATE id = id"
        );
        $stmt->execute([
            ':userId'      => $userId,
            ':eventId'     => $eventId,
            ':offset'      => $offset,
            ':occurrence'  => $occurrence->format(self::DATETIME),
            ':allDay'      => $allDay ? 1 : 0,
            ':title'       => mb_substr($title, 0, 255),
            ':emailStatus' => $emailStatus,
            ':nextAttempt' => $emailStatus === 'pending' ? $now->format(self::DATETIME) : null,
            ':now'         => $now->format(self::DATETIME),
        ]);
        return $stmt->rowCount() === 1;
    }

    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, event_id, offset_minutes, occurrence_at, all_day, title,
                    email_status, email_sent_at, read_at, created_at
             FROM notifications
             WHERE user_id = :userId
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :userId AND read_at IS NULL'
        );
        $stmt->execute([':userId' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $userId, int $id, DateTimeImmutable $now): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, :now)
             WHERE id = :id AND user_id = :userId'
        );
        $stmt->execute([':now' => $now->format(self::DATETIME), ':id' => $id, ':userId' => $userId]);
        return $stmt->rowCount() > 0 || $this->exists($userId, $id);
    }

    public function markAllRead(int $userId, DateTimeImmutable $now): int
    {
        $stmt = $this->db()->prepare(
            'UPDATE notifications SET read_at = :now WHERE user_id = :userId AND read_at IS NULL'
        );
        $stmt->execute([':now' => $now->format(self::DATETIME), ':userId' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * E-mails à envoyer maintenant, avec les données de l'utilisateur et l'état
     * ACTUEL de l'événement (qui a pu être modifié ou supprimé entre-temps).
     */
    public function emailQueue(DateTimeImmutable $now, int $limit): array
    {
        $stmt = $this->db()->prepare(
            "SELECT n.id, n.event_id, n.offset_minutes, n.occurrence_at, n.all_day AS notification_all_day,
                    n.title AS notification_title, n.email_attempts,
                    u.email, u.nomUtilisateur, u.email_reminders, u.is_confirmed,
                    e.id AS current_event_id, e.title, e.dateEvent, e.time, e.end_time, e.all_day,
                    e.location, e.category, e.descriptionEvent, e.recurrence, e.recurrence_until
             FROM notifications n
             JOIN users u ON u.id = n.user_id
             LEFT JOIN events e ON e.id = n.event_id
             WHERE n.email_status = 'pending'
               AND (n.email_next_attempt_at IS NULL OR n.email_next_attempt_at <= :now)
             ORDER BY n.email_next_attempt_at ASC, n.id ASC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute([':now' => $now->format(self::DATETIME)]);
        return $stmt->fetchAll();
    }

    public function countPendingEmails(DateTimeImmutable $now): int
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE email_status = 'pending'
               AND (email_next_attempt_at IS NULL OR email_next_attempt_at <= :now)"
        );
        $stmt->execute([':now' => $now->format(self::DATETIME)]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Réserve l'envoi (pending → sending) de façon atomique : une seule exécution
     * concurrente du cron peut obtenir true pour une même notification.
     */
    public function claimEmail(int $id, DateTimeImmutable $now): bool
    {
        $stmt = $this->db()->prepare(
            "UPDATE notifications
             SET email_status = 'sending', email_locked_at = :now, email_attempts = email_attempts + 1
             WHERE id = :id AND email_status = 'pending'"
        );
        $stmt->execute([':now' => $now->format(self::DATETIME), ':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function finishEmail(
        int $id,
        string $status,
        ?string $error = null,
        ?DateTimeImmutable $nextAttemptAt = null,
        ?DateTimeImmutable $sentAt = null
    ): void {
        $this->db()->prepare(
            'UPDATE notifications
             SET email_status = :status, email_error = :error, email_next_attempt_at = :next,
                 email_sent_at = COALESCE(:sentAt, email_sent_at), email_locked_at = NULL
             WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':error'  => $error === null ? null : mb_substr($error, 0, 255),
            ':next'   => $nextAttemptAt?->format(self::DATETIME),
            ':sentAt' => $sentAt?->format(self::DATETIME),
            ':id'     => $id,
        ]);
    }

    /**
     * Débloque les envois restés « sending » (script interrompu pendant l'envoi) :
     * nouvel essai si des tentatives restent, échec définitif sinon.
     */
    public function releaseStaleEmails(DateTimeImmutable $now): int
    {
        $stmt = $this->db()->prepare(
            "UPDATE notifications
             SET email_status = IF(email_attempts >= :max, 'failed', 'pending'),
                 email_error = 'Envoi interrompu avant confirmation du serveur SMTP.',
                 email_next_attempt_at = :now,
                 email_locked_at = NULL
             WHERE email_status = 'sending' AND email_locked_at < :limit"
        );
        $stmt->execute([
            ':max'   => self::EMAIL_MAX_ATTEMPTS,
            ':now'   => $now->format(self::DATETIME),
            ':limit' => $now->modify('-' . self::EMAIL_LOCK_MINUTES . ' minutes')->format(self::DATETIME),
        ]);
        return $stmt->rowCount();
    }

    public function purgeCreatedBefore(DateTimeImmutable $before): int
    {
        $stmt = $this->db()->prepare(
            "DELETE FROM notifications
             WHERE created_at < :before AND email_status NOT IN ('pending', 'sending')"
        );
        $stmt->execute([':before' => $before->format(self::DATETIME)]);
        return $stmt->rowCount();
    }

    private function exists(int $userId, int $id): bool
    {
        $stmt = $this->db()->prepare('SELECT 1 FROM notifications WHERE id = :id AND user_id = :userId');
        $stmt->execute([':id' => $id, ':userId' => $userId]);
        return (bool) $stmt->fetchColumn();
    }
}
