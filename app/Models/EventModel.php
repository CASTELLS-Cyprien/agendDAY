<?php
declare(strict_types=1);
namespace App\Models;

use DateTimeImmutable;

class EventModel extends BaseModel
{
    private const COLUMNS = 'id, userID, title, dateEvent, time, end_time, all_day, location, category,
                             descriptionEvent, recurrence, recurrence_until';

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM events WHERE id = :id AND userID = :userId'
        );
        $stmt->execute([':id' => $id, ':userId' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Événements ayant au moins une occurrence possible entre $from et $to (Y-m-d) :
     * événements simples de la période + séries commencées avant la fin de la période
     * et non terminées avant son début. Le calcul exact des occurrences est fait en PHP.
     */
    public function listInRange(int $userId, string $from, string $to): array
    {
        $stmt = $this->db()->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM events
             WHERE userID = :userId
               AND (
                    (recurrence = \'none\' AND dateEvent BETWEEN :from AND :to)
                 OR (recurrence <> \'none\' AND dateEvent <= :seriesTo
                     AND (recurrence_until IS NULL OR recurrence_until >= :seriesFrom))
               )
             ORDER BY dateEvent ASC, all_day DESC, time ASC'
        );
        $stmt->execute([
            ':userId'     => $userId,
            ':from'       => $from,
            ':to'         => $to,
            ':seriesTo'   => $to,
            ':seriesFrom' => $from,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Recherche dans le titre, la description et le lieu. La collation de la base
     * rend la recherche insensible à la casse et aux accents.
     */
    public function search(int $userId, string $query, int $limit = 30): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';

        $stmt = $this->db()->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM events
             WHERE userID = :userId
               AND (title LIKE :q1 OR descriptionEvent LIKE :q2 OR location LIKE :q3)
             ORDER BY dateEvent DESC, time ASC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute([':userId' => $userId, ':q1' => $like, ':q2' => $like, ':q3' => $like]);
        return $stmt->fetchAll();
    }

    /**
     * Crée l'événement et programme ses rappels dans une même transaction.
     */
    public function create(int $userId, array $data, DateTimeImmutable $now): int
    {
        return $this->transaction(function () use ($userId, $data, $now): int {
            $stmt = $this->db()->prepare(
                'INSERT INTO events
                    (userID, title, dateEvent, time, end_time, all_day, location, category,
                     descriptionEvent, recurrence, recurrence_until, sentReminder)
                 VALUES
                    (:userId, :title, :date, :time, :endTime, :allDay, :location, :category,
                     :description, :recurrence, :until, 0)'
            );
            $stmt->execute([':userId' => $userId] + $this->params($data));
            $id = (int) $this->db()->lastInsertId();

            (new ReminderModel())->replaceForEvent($id, $this->asRow($data), $data['reminders'], $now);
            return $id;
        });
    }

    /**
     * Met à jour l'événement (s'il appartient à l'utilisateur) et reprogramme ses rappels.
     * Retourne false si l'événement n'existe pas ou n'appartient pas à l'utilisateur.
     */
    public function update(int $id, int $userId, array $data, DateTimeImmutable $now): bool
    {
        return $this->transaction(function () use ($id, $userId, $data, $now): bool {
            $check = $this->db()->prepare('SELECT id FROM events WHERE id = :id AND userID = :userId FOR UPDATE');
            $check->execute([':id' => $id, ':userId' => $userId]);
            if (!$check->fetch()) {
                return false;
            }

            $stmt = $this->db()->prepare(
                'UPDATE events
                 SET title = :title, dateEvent = :date, time = :time, end_time = :endTime,
                     all_day = :allDay, location = :location, category = :category,
                     descriptionEvent = :description, recurrence = :recurrence,
                     recurrence_until = :until, sentReminder = 0
                 WHERE id = :id AND userID = :userId'
            );
            $stmt->execute([':id' => $id, ':userId' => $userId] + $this->params($data));

            (new ReminderModel())->replaceForEvent($id, $this->asRow($data), $data['reminders'], $now);
            return true;
        });
    }

    /**
     * Supprime l'événement et ses rappels programmés. Les notifications déjà émises
     * sont conservées (historique) mais détachées, et leurs e-mails en attente annulés.
     */
    public function delete(int $id, int $userId): bool
    {
        return $this->transaction(function () use ($id, $userId): bool {
            $check = $this->db()->prepare('SELECT id FROM events WHERE id = :id AND userID = :userId FOR UPDATE');
            $check->execute([':id' => $id, ':userId' => $userId]);
            if (!$check->fetch()) {
                return false;
            }

            // Redondant avec les clés étrangères (ON DELETE CASCADE / SET NULL), mais
            // garantit le même résultat si la base ne les a pas (tables MyISAM…).
            $this->db()->prepare('DELETE FROM event_reminders WHERE event_id = :id')->execute([':id' => $id]);
            $this->db()->prepare(
                "UPDATE notifications
                 SET email_status = IF(email_status IN ('pending', 'sending'), 'cancelled', email_status),
                     event_id = NULL
                 WHERE event_id = :id"
            )->execute([':id' => $id]);
            $this->db()->prepare('DELETE FROM events WHERE id = :id AND userID = :userId')
                ->execute([':id' => $id, ':userId' => $userId]);
            return true;
        });
    }

    /**
     * Purge de conservation : événements simples antérieurs à $cutoff et séries
     * terminées avant $cutoff. Les séries sans fin ne sont jamais purgées.
     */
    public function purgeEndedBefore(string $cutoff): int
    {
        return $this->transaction(function () use ($cutoff): int {
            $condition = "(recurrence = 'none' AND dateEvent < :c1)
                       OR (recurrence <> 'none' AND recurrence_until IS NOT NULL AND recurrence_until < :c2)";
            $params = [':c1' => $cutoff, ':c2' => $cutoff];

            $this->db()->prepare(
                "DELETE FROM event_reminders WHERE event_id IN (SELECT id FROM events WHERE {$condition})"
            )->execute($params);
            $this->db()->prepare(
                "UPDATE notifications SET event_id = NULL WHERE event_id IN (SELECT id FROM events WHERE {$condition})"
            )->execute($params);

            $stmt = $this->db()->prepare("DELETE FROM events WHERE {$condition}");
            $stmt->execute($params);
            return $stmt->rowCount();
        });
    }

    private function params(array $data): array
    {
        return [
            ':title'       => $data['title'],
            ':date'        => $data['date'],
            ':time'        => $data['time'],
            ':endTime'     => $data['end_time'],
            ':allDay'      => $data['all_day'] ? 1 : 0,
            ':location'    => $data['location'],
            ':category'    => $data['category'],
            ':description' => $data['description'],
            ':recurrence'  => $data['recurrence'],
            ':until'       => $data['recurrence_until'],
        ];
    }

    /** Données validées → format d'une ligne `events` (attendu par ReminderScheduler). */
    private function asRow(array $data): array
    {
        return [
            'dateEvent'        => $data['date'],
            'time'             => $data['time'],
            'all_day'          => $data['all_day'] ? 1 : 0,
            'recurrence'       => $data['recurrence'],
            'recurrence_until' => $data['recurrence_until'],
        ];
    }
}
