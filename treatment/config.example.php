<?php
// ============================================================
// CONFIG2.EXAMPLE.PHP — Template de configuration
// ➜ Copier ce fichier en config2.php et remplir les valeurs
// ============================================================

// --- Base de données ---
define('DB_HOST', 'votre-host.mysql.db');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

// --- SMTP ---
define('SMTP_HOST',       'ssl0.ovh.net');  // ou votre serveur SMTP
define('SMTP_PORT',       465);
define('SMTP_SECURE',     'ssl');
define('SMTP_USERNAME',   'votre@email.com');
define('SMTP_PASSWORD',   'votre_mot_de_passe_smtp');
define('EMAIL_FROM',      'votre@email.com');
define('EMAIL_FROM_NAME', 'AgendDAY');

// --- reCAPTCHA v2 ---
// Clé secrète depuis https://www.google.com/recaptcha/admin
define('SECRET_KEY', 'votre_cle_secrete_recaptcha');

// --- Token sécurisé pour le cron send_reminders ---
// Générer avec : php -r "echo bin2hex(random_bytes(32));"
define('CRON_SECRET', 'votre_token_cron_genere_aleatoirement');
