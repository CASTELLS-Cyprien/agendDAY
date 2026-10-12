<?php
declare(strict_types=1);

/*
 * Serveur de développement local :   php dev/serve.php
 * Puis ouvrir http://127.0.0.1:8000 (compte de démonstration : voir dev/seed.php).
 */

require __DIR__ . '/env.php';

$root    = dirname(__DIR__);
$command = escapeshellarg(PHP_BINARY) . ' -S ' . DEV_HOST . ':' . DEV_PORT
    . ' -t ' . escapeshellarg($root) . ' ' . escapeshellarg($root . '/dev/router.php');

echo 'AgendDAY (développement) : http://' . DEV_HOST . ':' . DEV_PORT . PHP_EOL;
passthru($command, $exitCode);
exit($exitCode);
