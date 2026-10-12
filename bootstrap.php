<?php
declare(strict_types=1);

/*
 * Initialisation commune à l'application web (index.php) et aux scripts cron.
 * Centralise : chargement de la configuration, valeurs par défaut, fuseau horaire,
 * affichage des erreurs et autoloaders.
 */

define('APP_ROOT', __DIR__);

// AGENDDAY_CONFIG permet de pointer vers une autre configuration (tests, développement)
// sans toucher au config.php de l'environnement.
$configFile = getenv('AGENDDAY_CONFIG') ?: __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    error_log('[agendday] Fichier de configuration introuvable.');
    exit('Configuration manquante.');
}
require_once $configFile;

// Réglages ajoutés après la mise en production : un config.php existant reste valide
// sans modification grâce à ces valeurs par défaut.
defined('APP_ENV')              || define('APP_ENV', 'production');
defined('APP_TIMEZONE')         || define('APP_TIMEZONE', 'Europe/Paris');
defined('MAIL_TRANSPORT')       || define('MAIL_TRANSPORT', 'smtp');
defined('MAIL_LOG_DIR')         || define('MAIL_LOG_DIR', __DIR__ . '/storage/mail');
defined('EVENT_RETENTION_DAYS') || define('EVENT_RETENTION_DAYS', 365);

// Toutes les dates (PHP et session MySQL, cf. BaseModel) sont exprimées dans ce fuseau.
date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');

// Autoloader Composer — dépendances tierces (PHPMailer)
require_once __DIR__ . '/vendor/autoload.php';

// Autoloader PSR-4 du projet
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\'  => __DIR__ . '/app/',
        'Core\\' => __DIR__ . '/core/',
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

Core\ErrorHandler::register();
