<?php
declare(strict_types=1);
/*
 * Traitement des rappels — à exécuter toutes les 5 minutes.
 *
 *   CLI  : php cron/send_reminders.php [--dry-run]
 *   HTTP : https://<domaine>/cron/send_reminders.php
 *          avec l'en-tête « X-Cron-Secret: <CRON_SECRET> » (cf. Core\Cron).
 *
 * --dry-run (ou ?dry-run=1) : compte les rappels et e-mails à traiter sans rien
 * modifier ni envoyer.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Models\CronLock;
use App\Services\ReminderService;
use Core\Cron;

Cron::authorize();

$dryRun  = in_array('--dry-run', $argv ?? [], true) || ($_GET['dry-run'] ?? '') === '1';
$now     = new DateTimeImmutable();
$service = new ReminderService();

if ($dryRun) {
    $preview = $service->preview($now);
    Cron::finish(sprintf(
        '[simulation] %d rappel(s) à échéance, %d e-mail(s) en attente — aucune modification effectuée.',
        $preview['due_reminders'],
        $preview['pending_emails']
    ));
}

$lockName = 'agendday_send_reminders';
$lock     = new CronLock();
if (!$lock->acquire($lockName)) {
    Cron::finish('Une exécution est déjà en cours — passage ignoré.');
}

try {
    $stats = $service->run($now);
} finally {
    $lock->release($lockName);
}

$message = sprintf(
    'OK — %d notification(s) créée(s), %d rappel(s) expiré(s) ; e-mails : %d accepté(s) par le serveur SMTP, '
    . '%d à réessayer, %d en échec définitif, %d annulé(s), %d débloqué(s) — %s',
    $stats['notifications'],
    $stats['expired'],
    $stats['emails_sent'],
    $stats['emails_retry'],
    $stats['emails_failed'],
    $stats['emails_cancelled'],
    $stats['emails_released'],
    $now->format('d/m/Y H:i:s')
);

error_log('[cron] ' . $message);
Cron::finish($message);
