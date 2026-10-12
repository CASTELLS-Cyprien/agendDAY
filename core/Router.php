<?php
declare(strict_types=1);
namespace Core;

class Router
{
    private array $routes = [];

    public function __construct()
    {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->routes = [
            ['GET',  '/',                             'App\Controllers\HomeController',         'index'],
            ['GET',  '/connexion',                    'App\Controllers\AuthController',         'showLogin'],
            ['POST', '/connexion',                    'App\Controllers\AuthController',         'login'],
            ['GET',  '/inscription',                  'App\Controllers\AuthController',         'showRegister'],
            ['POST', '/inscription',                  'App\Controllers\AuthController',         'register'],
            ['GET',  '/deconnexion',                  'App\Controllers\AuthController',         'logoutLink'],
            ['POST', '/deconnexion',                  'App\Controllers\AuthController',         'logout'],
            ['GET',  '/mot-de-passe-oublie',          'App\Controllers\AuthController',         'showForgotPassword'],
            ['POST', '/mot-de-passe-oublie',          'App\Controllers\AuthController',         'forgotPassword'],
            ['GET',  '/reinitialiser-mot-de-passe',   'App\Controllers\AuthController',         'showResetPassword'],
            ['POST', '/reinitialiser-mot-de-passe',   'App\Controllers\AuthController',         'resetPassword'],
            ['GET',  '/confirmation',                 'App\Controllers\AuthController',         'confirm'],
            ['GET',  '/calendrier',                   'App\Controllers\CalendarController',     'index'],
            ['GET',  '/parametres',                   'App\Controllers\SettingsController',     'show'],
            ['POST', '/parametres',                   'App\Controllers\SettingsController',     'update'],
            ['POST', '/parametres/supprimer-compte',  'App\Controllers\SettingsController',     'deleteAccount'],
            ['GET',  '/api/events',                   'App\Controllers\EventController',        'index'],
            ['GET',  '/api/events/search',            'App\Controllers\EventController',        'search'],
            ['POST', '/api/events',                   'App\Controllers\EventController',        'store'],
            ['POST', '/api/events/update',            'App\Controllers\EventController',        'update'],
            ['POST', '/api/events/delete',            'App\Controllers\EventController',        'delete'],
            ['GET',  '/api/notifications',            'App\Controllers\NotificationController', 'index'],
            ['POST', '/api/notifications/read',       'App\Controllers\NotificationController', 'markRead'],
            ['GET',  '/contact',                      'App\Controllers\ContactController',      'show'],
            ['POST', '/contact',                      'App\Controllers\ContactController',      'send'],
            ['GET',  '/mentions-legales',             'App\Controllers\PageController',         'mentions'],
            ['GET',  '/politique-confidentialite',    'App\Controllers\PageController',         'confidentialite'],
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = strtok($uri, '?') ?: '/';

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // HEAD est traité comme GET (le serveur n'envoie pas le corps)
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $allowed = [];
        foreach ($this->routes as [$routeMethod, $pattern, $controllerClass, $action]) {
            if ($path !== $pattern) {
                continue;
            }
            if ($routeMethod === $method) {
                (new $controllerClass())->$action();
                return;
            }
            $allowed[] = $routeMethod;
        }

        if ($allowed !== []) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowed)));
            echo 'Méthode non autorisée.';
            return;
        }

        (new \App\Controllers\ErrorController())->notFound();
    }
}
