<?php
declare(strict_types=1);
namespace App\Models;

use App\Services\ReminderScheduler;
use DateTimeImmutable;

class ReminderModel extends BaseModel
{
    private const DATETIME = 'Y-m-d H:i:s';

    /**
     * Remplace les rappels d'un événement et calcule la prochaine échéance de chacun.
     *
     * @param array     $event   ligne au format `events` (dateEvent, time, all_day, recurrence…)
     * @param list<int> $offsets délais en minutes
     */
    public function replaceForEvent(int $eventId, array $event, array $offsets, DateTimeImmutable $now): void
    {
        $this->db()->prepare('DELETE FROM event_reminders WHERE event_id = :id')->execute([':id' => $eventId]);

        $insert = $this->db()->prepare(
            'INSERT INTO event_reminders (event_id, offset_minutes, occurrence_at, remind_at)
             VALUES (:eventId, :offset, :occurrence, :remindAt)'
        );

        foreach ($offsets as $offset) {
            $slot = ReminderScheduler::initial($event, (int) $offset, $now);
            $insert->execute([
                ':eventId'    => $eventId,
                ':offset'     => (int) $offset,
                ':occurrence' => $slot ? $slot['occurrence']->format(self::DATETIME) : null,
                ':remindAt'   => $slot ? $slot['remindAt']->format(self::DATETIME) : null,
            ]);
        }
    }

    /**
     * @param list<int> $eventIds
     * @return array<int, list<int>> délais par identifiant d'événement
     */
    public function offsetsByEvent(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->db()->prepare(
            "SELECT event_id, offset_minutes FROM event_reminders
             WHERE event_id IN ({$placeholders}) ORDER BY offset_minutes"
        );
        $stmt->execute(array_values(array_map('intval', $eventIds)));

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['event_id']][] = (int) $row['offset_minutes'];
        }
        return $map;
    }

    /**
     * Rappels arrivés à échéance (y compris en retard).
     */
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, remind_at FROM event_reminders
             WHERE remind_at IS NOT NULL AND remind_at <= :now
             ORDER BY remind_at ASC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute([':now' => $now->format(self::DATETIME)]);
        return $stmt->fetchAll();
    }

    public function countDue(DateTimeImmutable $now): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM event_reminders WHERE remind_at IS NOT NULL AND remind_at <= :now'
        );
        $stmt->execute([':now' => $now->format(self::DATETIME)]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verrouille un rappel (à appeler dans une transaction) et retourne les données
     * nécessaires à son traitement : rappel + événement + préférences de l'utilisateur.
     */
    public function lockForProcessing(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT r.id, r.event_id, r.offset_minutes, r.occurrence_at, r.remind_at,
                    e.userID, e.title, e.dateEvent, e.time, e.all_day, e.recurrence, e.recurrence_until,
                    u.email_reminders, u.is_confirmed
             FROM event_reminders r
             JOIN events e ON e.id = r.event_id
             JOIN users u ON u.id = e.userID
             WHERE r.id = :id
             FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function reschedule(int $id, ?DateTimeImmutable $occurrence, ?DateTimeImmutable $remindAt): void
    {
        $this->db()->prepare(
            'UPDATE event_reminders SET occurrence_at = :occurrence, remind_at = :remindAt WHERE id = :id'
        )->execute([
            ':occurrence' => $occurrence?->format(self::DATETIME),
            ':remindAt'   => $remindAt?->format(self::DATETIME),
            ':id'         => $id,
        ]);
    }
}
