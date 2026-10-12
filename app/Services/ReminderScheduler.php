<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;

/**
 * Règles de planification des rappels (fonctions pures, sans accès à la base).
 *
 * Pour chaque occurrence d'un événement :
 *  - heure de référence : l'heure de début, ou ALL_DAY_REMINDER_TIME (09:00) pour
 *    un événement sur la journée entière ;
 *  - heure du rappel : heure de référence − délai ;
 *  - expiration : au-delà, un rappel en retard n'a plus d'intérêt et n'est pas
 *    envoyé (15 min après le début, ou fin de journée pour une journée entière).
 *
 * Les tableaux $event utilisent les noms de colonnes de la table `events`.
 */
final class ReminderScheduler
{
    /** Tolérance après le début d'un événement pour délivrer un rappel en retard. */
    public const LATE_GRACE_MINUTES = 15;

    public static function referenceTime(array $event, string $date): DateTimeImmutable
    {
        $time = !empty($event['all_day'])
            ? EventOptions::ALL_DAY_REMINDER_TIME
            : substr((string) $event['time'], 0, 5);

        return new DateTimeImmutable($date . ' ' . $time);
    }

    public static function expiry(array $event, string $date): DateTimeImmutable
    {
        if (!empty($event['all_day'])) {
            return (new DateTimeImmutable($date))->modify('+1 day');
        }
        return self::referenceTime($event, $date)->modify('+' . self::LATE_GRACE_MINUTES . ' minutes');
    }

    /**
     * Planification à l'enregistrement : première occurrence dont l'heure de rappel
     * est encore à venir. Un rappel dont l'heure est déjà passée au moment de la
     * création n'est pas programmé (pas d'e-mail « surprise » immédiat).
     *
     * @return array{occurrence: DateTimeImmutable, remindAt: DateTimeImmutable}|null
     */
    public static function initial(array $event, int $offset, DateTimeImmutable $now): ?array
    {
        $from = $now->modify("+{$offset} minutes")->modify('-1 day')->format('Y-m-d');

        $date = Recurrence::first(
            $event['dateEvent'],
            $event['recurrence'] ?? 'none',
            $event['recurrence_until'] ?? null,
            $from,
            fn (string $d): bool => self::referenceTime($event, $d)->modify("-{$offset} minutes") > $now
        );

        return $date === null ? null : self::slot($event, $date, $offset);
    }

    /**
     * Après traitement de l'occurrence du $currentDate : prochaine occurrence
     * pour laquelle un rappel a encore un sens (rattrape un cron resté arrêté
     * sans envoyer une rafale de rappels périmés).
     *
     * @return array{occurrence: DateTimeImmutable, remindAt: DateTimeImmutable}|null
     */
    public static function next(array $event, int $offset, string $currentDate, DateTimeImmutable $now): ?array
    {
        $dayAfter  = (new DateTimeImmutable($currentDate))->modify('+1 day')->format('Y-m-d');
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $from      = max($dayAfter, $yesterday);

        $date = Recurrence::first(
            $event['dateEvent'],
            $event['recurrence'] ?? 'none',
            $event['recurrence_until'] ?? null,
            $from,
            fn (string $d): bool => self::expiry($event, $d) > $now
        );

        return $date === null ? null : self::slot($event, $date, $offset);
    }

    /**
     * @return array{occurrence: DateTimeImmutable, remindAt: DateTimeImmutable}
     */
    private static function slot(array $event, string $date, int $offset): array
    {
        $occurrence = self::referenceTime($event, $date);
        return ['occurrence' => $occurrence, 'remindAt' => $occurrence->modify("-{$offset} minutes")];
    }
}
