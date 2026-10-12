<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\NotificationModel;
use DateTimeImmutable;

/**
 * Centre de notifications : uniquement des notifications réellement créées par
 * le moteur de rappels (cron/send_reminders.php).
 */
class NotificationController extends BaseController
{
    public function index(): void
    {
        $this->requireAuthApi();

        $model = new NotificationModel();
        $items = array_map(static function (array $row): array {
            $occurrence = $row['occurrence_at'] ? new DateTimeImmutable($row['occurrence_at']) : null;
            $allDay     = (int) $row['all_day'] === 1;

            return [
                'id'          => (int) $row['id'],
                'eventId'     => $row['event_id'] !== null ? (int) $row['event_id'] : null,
                'title'       => (string) $row['title'],
                'date'        => $occurrence?->format('Y-m-d'),
                'time'        => ($occurrence && !$allDay) ? $occurrence->format('H:i') : null,
                'allDay'      => $allDay,
                'offset'      => $row['offset_minutes'] !== null ? (int) $row['offset_minutes'] : null,
                'emailStatus' => (string) $row['email_status'],
                'read'        => $row['read_at'] !== null,
                'createdAt'   => (new DateTimeImmutable($row['created_at']))->format(DATE_ATOM),
            ];
        }, $model->recentForUser($this->userId(), 30));

        $this->json([
            'unread' => $model->unreadCount($this->userId()),
            'items'  => $items,
        ]);
    }

    /**
     * POST /api/notifications/read — id=<n> pour une notification, all=1 pour toutes.
     */
    public function markRead(): void
    {
        $this->requireAuthApi();
        $this->requireCsrfApi();

        $model = new NotificationModel();
        $now   = new DateTimeImmutable();

        if (($_POST['all'] ?? '') === '1') {
            $model->markAllRead($this->userId(), $now);
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || !$model->markRead($this->userId(), $id, $now)) {
                $this->json(['error' => 'Notification introuvable.'], 404);
            }
        }

        $this->json(['success' => true, 'unread' => $model->unreadCount($this->userId())]);
    }
}
