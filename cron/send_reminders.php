<?php
declare(strict_types=1);
// URL : https://agendday.castells-cyprien.ovh/cron/send_reminders.php?secret=CRON_SECRET

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/../config/config.php';

// Autoloader PSR-4 du projet — permet d'utiliser EventModel et MailService
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\'  => __DIR__ . '/../app/',
        'Core\\' => __DIR__ . '/../core/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file     = $baseDir . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }
});

use App\Models\BaseModel;
use App\Services\MailService;

// Sécurité : vérification du secret (sauf exécution CLI)
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals(CRON_SECRET, $secret)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
}

try {
    // Réutilise la connexion PDO centralisée via BaseModel
    $bdd = (new class extends BaseModel {
        public function getDb(): \PDO { return $this->db(); }
    })->getDb();
} catch (\PDOException $e) {
    error_log('[cron] Erreur DB : ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur DB');
}

$now      = new DateTime('now', new DateTimeZone('Europe/Paris'));
$testMode  = isset($_GET['test']) && $_GET['test'] === '1';
$startTime = $now->format('Y-m-d H:i:s');
$endTime   = (clone $now)->modify($testMode ? '+24 hours' : '+5 minutes')->format('Y-m-d H:i:s');

if ($testMode) {
    echo "[TEST] Fenêtre élargie à 24h : {$startTime} → {$endTime}\n";
}

$stmt = $bdd->prepare(
    "SELECT e.id, e.title, e.dateEvent, e.time, e.descriptionEvent,
            u.email, u.nomUtilisateur
     FROM events e
     JOIN users u ON e.userID = u.id
     WHERE CONCAT(e.dateEvent, ' ', e.time) BETWEEN :start AND :end
       AND e.dateEvent >= CURDATE()
       AND (e.sentReminder IS NULL OR e.sentReminder = FALSE)
       AND u.is_confirmed = 1"
);
$stmt->execute([':start' => $startTime, ':end' => $endTime]);
$events = $stmt->fetchAll();

if (empty($events)) {
    echo 'OK — 0 rappel à envoyer.';
    exit;
}

$mailService = new MailService();
$sent        = 0;
$fails       = 0;

foreach ($events as $event) {
    if (!filter_var($event['email'], FILTER_VALIDATE_EMAIL)) {
        error_log('[cron] Email invalide — ID ' . $event['id']);
        continue;
    }

    $lockFile = sys_get_temp_dir() . '/reminder_' . $event['id'] . '.lock';
    if (file_exists($lockFile) && time() - filemtime($lockFile) < 600) {
        continue;
    }
    touch($lockFile);

    // try/finally : garantit la suppression du verrou même si une erreur fatale
    // (Throwable non Exception, ex. TypeError) interrompt le traitement de cet
    // événement — sans attendre l'expiration du verrou (10 min) au tour suivant.
    try {
        $eventDt      = new DateTime($event['dateEvent'] . ' ' . $event['time'], new DateTimeZone('Europe/Paris'));
        $diff         = $now->diff($eventDt);
        $tempsRestant = $diff->h > 0 ? "dans {$diff->h}h{$diff->i}min" : "dans {$diff->i} minute(s)";

        $success   = false;
        $lastError = '';
        for ($attempt = 1; $attempt <= 3 && !$success; $attempt++) {
            try {
                $mailService->sendReminder($event['email'], $event['nomUtilisateur'], $event, $tempsRestant);

                $bdd->prepare("UPDATE events SET sentReminder = TRUE WHERE id = :id")
                    ->execute([':id' => $event['id']]);

                $sent++;
                $success = true;
                error_log('[cron] Rappel envoyé — ID ' . $event['id'] . ' à ' . $event['email']);
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                error_log('[cron] Tentative ' . $attempt . ' échouée — ID ' . $event['id'] . ' : ' . $lastError);
                if ($attempt < 3) {
                    sleep(2);
                }
            }
        }

        if (!$success) {
            $fails++;
            error_log('[cron] Échec définitif — ID ' . $event['id']);
            echo "\n[ERREUR ID {$event['id']}] " . $lastError;
        }
    } finally {
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}

$msg = "OK — {$sent} rappel(s) envoyé(s), {$fails} échec(s) — " . $now->format('d/m/Y H:i:s');
error_log('[cron] ' . $msg);
echo $msg;
