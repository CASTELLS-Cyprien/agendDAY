<?php
declare(strict_types=1);

// Configuration des tests automatisés et du serveur de développement local.
// Base MySQL locale dédiée (recréée par les tests), aucun e-mail réellement envoyé.
// Les valeurs peuvent être surchargées par variables d'environnement.

define('APP_URL', getenv('AGENDDAY_TEST_URL') ?: 'http://127.0.0.1:8765');
define('APP_ENV', 'development');
define('APP_TIMEZONE', 'Europe/Paris');

define('DB_HOST', getenv('AGENDDAY_TEST_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('AGENDDAY_TEST_DB_NAME') ?: 'agendday_test');
define('DB_USER', getenv('AGENDDAY_TEST_DB_USER') ?: 'root');
define('DB_PASS', getenv('AGENDDAY_TEST_DB_PASS') ?: '');

define('MAIL_TRANSPORT', 'log');
define('MAIL_LOG_DIR', getenv('AGENDDAY_TEST_MAIL_DIR') ?: sys_get_temp_dir() . '/agendday-test-mail');
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_SECURE', '');
define('SMTP_USERNAME', 'test');
define('SMTP_PASSWORD', 'test');
define('EMAIL_FROM', 'noreply@agendday.test');
define('EMAIL_FROM_NAME', 'AgendDAY (test)');

define('SECRET_KEY', 'test-recaptcha-secret');
define('RECAPTCHA_SITE_KEY', 'test-recaptcha-site-key');

define('CRON_SECRET', 'test-cron-secret');
define('EVENT_RETENTION_DAYS', 365);
