<?php
declare(strict_types=1);
/*
 * Purge de conservation des données — à exécuter une fois par jour.
 *
 *   CLI  : php cron/delete_past_events.php
 *   HTTP : https://<domaine>/cron/delete_past_events.php (en-tête X-Cron-Secret)
 *
 * Supprime les événements terminés depuis plus de EVENT_RETENTION_DAYS jours
 * (365 par défaut ; 0 = dès le lendemain, comportement historique) et les
 * notifications de plus de 90 jours. Les séries récurrentes sans date de fin
 * ne sont jamais supprimées.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Models\EventModel;
use App\Models\NotificationModel;
use Core\Cron;

Cron::authorize();

const NOTIFICATION_RETENTION_DAYS = 90;

$now       = new DateTimeImmutable();
$retention = max(0, (int) EVENT_RETENTION_DAYS);
$cutoff    = $now->modify("-{$retention} days")->format('Y-m-d');

$events        = (new EventModel())->purgeEndedBefore($cutoff);
$notifications = (new NotificationModel())->purgeCreatedBefore(
    $now->modify('-' . NOTIFICATION_RETENTION_DAYS . ' days')
);

$message = sprintf(
    'OK — %d événement(s) terminé(s) avant le %s et %d notification(s) supprimé(s) — %s',
    $events,
    (new DateTimeImmutable($cutoff))->format('d/m/Y'),
    $notifications,
    $now->format('d/m/Y H:i:s')
);

error_log('[cron] ' . $message);
Cron::finish($message);
