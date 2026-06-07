<?php
declare(strict_types=1);

// --- Application ---
define('APP_URL', 'https://agendday.castells-cyprien.ovh');

// --- Base de données ---
define('DB_HOST', 'votre-host.mysql.db');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

// --- SMTP ---
define('SMTP_HOST',       'ssl0.ovh.net');
define('SMTP_PORT',       465);
define('SMTP_SECURE',     'ssl');
define('SMTP_USERNAME',   'votre@email.com');
define('SMTP_PASSWORD',   'votre_mot_de_passe_smtp');
define('EMAIL_FROM',      'votre@email.com');
define('EMAIL_FROM_NAME', 'AgendDAY');

// --- reCAPTCHA v2 ---
// Clé secrète (privée) : https://www.google.com/recaptcha/admin
define('SECRET_KEY',        'votre_cle_secrete_recaptcha');
// Clé publique (site key, visible dans le HTML)
define('RECAPTCHA_SITE_KEY', 'votre_cle_publique_recaptcha');

// --- Cron ---
// Générer avec : php -r "echo bin2hex(random_bytes(32));"
define('CRON_SECRET', 'votre_token_cron_genere_aleatoirement');
