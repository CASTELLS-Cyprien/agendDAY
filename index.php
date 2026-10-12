<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Détection HTTPS — inclut le cas d'un proxy/load-balancer (OVH) qui termine le TLS
// en amont et transmet la requête en HTTP en interne avec l'en-tête X-Forwarded-Proto,
// sans quoi le cookie de session perdrait son attribut Secure.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? null) === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// Refuse les identifiants de session non émis par le serveur (fixation de session).
ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

$router = new Core\Router();
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
