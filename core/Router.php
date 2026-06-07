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
            ['GET',  '/',                             'App\Controllers\HomeController',     'index'],
            ['GET',  '/connexion',                    'App\Controllers\AuthController',     'showLogin'],
            ['POST', '/connexion',                    'App\Controllers\AuthController',     'login'],
            ['GET',  '/inscription',                  'App\Controllers\AuthController',     'showRegister'],
            ['POST', '/inscription',                  'App\Controllers\AuthController',     'register'],
            ['GET',  '/deconnexion',                  'App\Controllers\AuthController',     'logout'],
            ['GET',  '/mot-de-passe-oublie',          'App\Controllers\AuthController',     'showForgotPassword'],
            ['POST', '/mot-de-passe-oublie',          'App\Controllers\AuthController',     'forgotPassword'],
            ['GET',  '/reinitialiser-mot-de-passe',   'App\Controllers\AuthController',     'showResetPassword'],
            ['POST', '/reinitialiser-mot-de-passe',   'App\Controllers\AuthController',     'resetPassword'],
            ['GET',  '/confirmation',                 'App\Controllers\AuthController',     'confirm'],
            ['GET',  '/calendrier',                   'App\Controllers\CalendarController', 'index'],
            ['GET',  '/api/events',                   'App\Controllers\EventController',    'getAll'],
            ['POST', '/api/events',                   'App\Controllers\EventController',    'store'],
            ['POST', '/api/events/update',            'App\Controllers\EventController',    'update'],
            ['POST', '/api/events/delete',            'App\Controllers\EventController',    'delete'],
            ['POST', '/api/events/by-date',           'App\Controllers\EventController',    'getByDate'],
            ['GET',  '/contact',                      'App\Controllers\ContactController',  'show'],
            ['POST', '/contact',                      'App\Controllers\ContactController',  'send'],
            ['GET',  '/mentions-legales',             'App\Controllers\PageController',     'mentions'],
            ['GET',  '/politique-confidentialite',    'App\Controllers\PageController',     'confidentialite'],
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = strtok($uri, '?');

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as [$routeMethod, $pattern, $controllerClass, $action]) {
            if ($routeMethod === $method && $path === $pattern) {
                (new $controllerClass())->$action();
                return;
            }
        }

        http_response_code(404);
        echo '404 — Page introuvable';
    }
}
