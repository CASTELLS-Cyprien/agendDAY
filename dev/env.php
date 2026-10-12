<?php
declare(strict_types=1);

/*
 * Environnement de développement local : configuration de tests/config.testing.php
 * (MySQL local, e-mails écrits dans un dossier au lieu d'être envoyés) appliquée
 * à une base dédiée « agendday_dev ». Ne touche jamais au config/config.php.
 */

const DEV_HOST = '127.0.0.1';
const DEV_PORT = 8000;

foreach ([
    'AGENDDAY_CONFIG'        => dirname(__DIR__) . '/tests/config.testing.php',
    'AGENDDAY_TEST_DB_NAME'  => 'agendday_dev',
    'AGENDDAY_TEST_URL'      => 'http://' . DEV_HOST . ':' . DEV_PORT,
    'AGENDDAY_TEST_MAIL_DIR' => dirname(__DIR__) . '/storage/mail',
] as $name => $value) {
    if (getenv($name) === false) {
        putenv("{$name}={$value}");
    }
}
