<?php
declare(strict_types=1);
namespace App\Models;

class EventModel extends BaseModel
{
    public function getAllByUser(int $userId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT id, title, time, descriptionEvent,
                    DAY(dateEvent) AS day, MONTH(dateEvent) AS month, YEAR(dateEvent) AS year
             FROM events
             WHERE userID = :userId
             ORDER BY dateEvent ASC, time ASC"
        );
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getByDate(int $userId, string $date): array
    {
        $stmt = $this->db()->prepare(
            "SELECT id, title, time, descriptionEvent
             FROM events
             WHERE dateEvent = :date AND userID = :userId
             ORDER BY time ASC"
        );
        $stmt->execute([':date' => $date, ':userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT id FROM events WHERE id = :id AND userID = :userId"
        );
        $stmt->execute([':id' => $id, ':userId' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(
        int $userId,
        string $title,
        string $time,
        string $date,
        string $description
    ): int {
        $stmt = $this->db()->prepare(
            "INSERT INTO events (title, time, descriptionEvent, userID, dateEvent, sentReminder)
             VALUES (:title, :time, :description, :userId, :date, 0)"
        );
        $stmt->execute([
            ':title'       => $title,
            ':time'        => $time,
            ':description' => $description,
            ':userId'      => $userId,
            ':date'        => $date,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function update(
        int $id,
        int $userId,
        string $title,
        string $time,
        string $date,
        string $description
    ): void {
        $stmt = $this->db()->prepare(
            "UPDATE events
             SET title = :title, time = :time, descriptionEvent = :description,
                 dateEvent = :date, sentReminder = 0
             WHERE id = :id AND userID = :userId"
        );
        $stmt->execute([
            ':title'       => $title,
            ':time'        => $time,
            ':description' => $description,
            ':date'        => $date,
            ':id'          => $id,
            ':userId'      => $userId,
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare(
            "DELETE FROM events WHERE id = :id AND userID = :userId"
        );
        $stmt->execute([':id' => $id, ':userId' => $userId]);
        return $stmt->rowCount() > 0;
    }
}
