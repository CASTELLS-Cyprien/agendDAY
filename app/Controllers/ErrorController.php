<?php
declare(strict_types=1);
namespace App\Controllers;

class ErrorController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (str_starts_with($path, '/api/')) {
            $this->json(['error' => 'Ressource introuvable.'], 404);
        }

        $this->render('errors/404', [
            'flash'     => null,
            'metaTitle' => 'Page introuvable — AgendDAY',
            'isLoggedIn' => isset($_SESSION['id']),
        ], 'minimal');
    }
}
