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

    /**
     * Jeton CSRF lié à la session — à inclure dans tout formulaire / requête POST.
     */
    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? '';
        return is_string($submitted)
            && $submitted !== ''
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $submitted);
    }

    /**
     * À appeler en tête des actions POST classiques (formulaires HTML).
     */
    protected function requireCsrf(string $redirectTo): void
    {
        if (!$this->verifyCsrf()) {
            $this->flash('Requête invalide ou expirée. Veuillez réessayer.');
            $this->redirect($redirectTo);
        }
    }

    /**
     * À appeler en tête des actions POST de l'API JSON.
     */
    protected function requireCsrfApi(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['error' => 'Jeton de sécurité invalide. Veuillez recharger la page.'], 403);
        }
    }

    /**
     * Limiteur de tentatives basé sur des fichiers temporaires (pas de table dédiée
     * nécessaire) — même approche que les verrous utilisés par cron/send_reminders.php.
     * Retourne false si la limite est dépassée pour la fenêtre de temps donnée.
     */
    protected function rateLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $file = sys_get_temp_dir() . '/agendday_rl_' . hash('sha256', $key) . '.json';

        $now   = time();
        $state = ['count' => 0, 'start' => $now];

        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
                $state = $decoded;
            }
        }

        if ($now - (int) $state['start'] > $windowSeconds) {
            $state = ['count' => 0, 'start' => $now];
        }

        $state['count']++;
        @file_put_contents($file, json_encode($state), LOCK_EX);

        return $state['count'] <= $maxAttempts;
    }
}
