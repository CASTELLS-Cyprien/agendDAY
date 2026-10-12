<?php
declare(strict_types=1);
namespace Core;

/**
 * Gestion centralisée des exceptions non interceptées : journalisation complète côté
 * serveur, réponse générique côté client (aucun détail technique en production).
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(\Throwable $e): void
    {
        error_log(sprintf(
            '[agendday] %s : %s (%s:%d)',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (str_starts_with($path, '/api/')) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'Erreur interne. Veuillez réessayer dans un instant.']);
            return;
        }

        if (APP_ENV === 'development') {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
            return;
        }

        require APP_ROOT . '/app/Views/errors/500.php';
    }
}
