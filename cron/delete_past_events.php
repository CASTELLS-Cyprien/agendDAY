<?php
declare(strict_types=1);
// Appelé quotidiennement par cron-job.org ou via CLI
// URL : https://agendday.castells-cyprien.ovh/cron/delete_past_events.php?secret=CRON_SECRET

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/../config/config.php';

// Autoloader PSR-4 du projet — permet de réutiliser BaseModel (même connexion
// PDO centralisée que le reste de l'application, plutôt qu'une config dupliquée)
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

// Sécurité : vérification du secret (sauf exécution CLI)
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals(CRON_SECRET, $secret)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
}

try {
    $bdd = (new class extends BaseModel {
        public function getDb(): \PDO { return $this->db(); }
    })->getDb();
} catch (\PDOException $e) {
    error_log('[cron] Erreur DB : ' . $e->getMessage());
    http_response_code(500);
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
