<?php
declare(strict_types=1);

// Copier ce fichier en config/config.php et renseigner les valeurs.
// config/config.php n'est jamais versionné (cf. .gitignore).

// --- Application ---
define('APP_URL', 'https://agendday.castells-cyprien.ovh');
// 'production' (erreurs masquées) ou 'development' (erreurs affichées)
define('APP_ENV', 'production');
// Fuseau horaire de référence des événements et des rappels
define('APP_TIMEZONE', 'Europe/Paris');

// --- Base de données ---
define('DB_HOST', 'votre-host.mysql.db');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

// --- E-mails ---
// 'smtp' : envoi réel ; 'log' : les messages sont écrits dans MAIL_LOG_DIR (développement)
define('MAIL_TRANSPORT', 'smtp');
// define('MAIL_LOG_DIR', __DIR__ . '/../storage/mail');
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
// Secret attendu dans l'en-tête X-Cron-Secret des appels HTTP aux scripts de cron/.
// Générer avec : php -r "echo bin2hex(random_bytes(32));"
define('CRON_SECRET', 'votre_token_cron_genere_aleatoirement');

// --- Conservation des données ---
// Nombre de jours pendant lesquels un événement terminé est conservé avant
// suppression automatique (0 = suppression dès le lendemain).
define('EVENT_RETENTION_DAYS', 365);
