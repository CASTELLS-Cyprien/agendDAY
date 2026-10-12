<?php
declare(strict_types=1);

/*
 * Routeur du serveur PHP intégré — DÉVELOPPEMENT UNIQUEMENT.
 * Reproduit les règles utiles de .htaccess (non lu par ce serveur) :
 *
 *   php -S 127.0.0.1:8000 -t . dev/router.php
 */

$root = realpath(__DIR__ . '/..');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (preg_match('#^/(app|core|config|vendor|database|storage|tests|dev|docs)(/|$)#', $path)
    || preg_match('#(^|/)\.#', $path)
    || preg_match('#^/(bootstrap\.php|composer\.(json|lock)|README\.md)$#', $path)) {
    http_response_code(403);
    echo 'Accès interdit.';
    return true;
}

$file = realpath($root . $path);
if ($path !== '/' && $file !== false && str_starts_with($file, $root) && is_file($file)) {
    return false; // fichier existant (assets, scripts cron) : servi tel quel
}

require $root . '/index.php';
return true;
