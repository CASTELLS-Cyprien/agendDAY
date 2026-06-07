<?php
declare(strict_types=1);
// Appelé quotidiennement par cron-job.org ou via CLI
// URL : https://agendday.castells-cyprien.ovh/cron/delete_past_events.php?secret=CRON_SECRET

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/../config/config.php';

// Sécurité : vérification du secret (sauf exécution CLI)
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals(CRON_SECRET, $secret)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
}

try {
    $bdd = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[cron] Erreur DB : ' . $e->getMessage());
    exit('Erreur DB');
}

$now    = new DateTime('now', new DateTimeZone('Europe/Paris'));
$nowStr = $now->format('Y-m-d H:i:s');

$stmt = $bdd->prepare("DELETE FROM events WHERE CONCAT(dateEvent, ' ', time) < :now");
$stmt->execute([':now' => $nowStr]);
$count = $stmt->rowCount();

$msg = "[cron] {$count} événement(s) supprimé(s) à {$nowStr}";
error_log($msg);
echo $msg;
