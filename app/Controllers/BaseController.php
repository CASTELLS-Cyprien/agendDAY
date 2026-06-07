<?php
declare(strict_types=1);
namespace App\Controllers;

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'base'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/' . $layout . '.php';
    }

    /**
     * Ajoute un paramètre de version (filemtime) à l'URL d'un asset
     * pour forcer le rechargement par le navigateur après une mise à jour.
     */
    protected function asset(string $path): string
    {
        $file = __DIR__ . '/../../' . ltrim($path, '/');
        $version = file_exists($file) ? (string) filemtime($file) : (string) time();

        return $path . '?v=' . $version;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function requireAuth(): void
    {
        if (!isset($_SESSION['id'])) {
            $this->redirect('/connexion');
        }
    }

    protected function requireAuthApi(): void
    {
        if (!isset($_SESSION['id'])) {
            $this->json(['error' => 'Session expirée. Veuillez vous reconnecter.'], 401);
        }
    }

    protected function requireGuest(): void
    {
        if (isset($_SESSION['id'])) {
            $this->redirect('/calendrier');
        }
    }

    protected function flash(string $message): void
    {
        $_SESSION['flash'] = $message;
    }

    protected function getFlash(): ?string
    {
        $message = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $message;
    }
}
