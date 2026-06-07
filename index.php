<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

// Autoloader Composer — dépendances tierces (PHPMailer, etc.)
require_once __DIR__ . '/vendor/autoload.php';

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

// Détection HTTPS — inclut le cas d'un proxy/load-balancer (OVH) qui termine
// le TLS en amont et transmet la requête en HTTP en interne avec l'en-tête
// X-Forwarded-Proto, sans quoi le cookie de session perdrait son attribut Secure.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? null) === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

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
