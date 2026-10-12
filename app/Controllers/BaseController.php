<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\UserModel;

abstract class BaseController
{
    private ?array $currentUser = null;

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
        // Données personnelles : jamais mises en cache par le navigateur ou un proxy
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function userId(): int
    {
        return (int) ($_SESSION['id'] ?? 0);
    }

    /**
     * Utilisateur connecté (profil + préférences), chargé une fois par requête.
     * Si le compte n'existe plus, la session est fermée.
     */
    protected function currentUser(): ?array
    {
        if ($this->currentUser === null && isset($_SESSION['id'])) {
            $this->currentUser = (new UserModel())->findById($this->userId());
            if ($this->currentUser === null) {
                session_unset();
            }
        }
        return $this->currentUser;
    }

    protected function requireAuth(): void
    {
        if (!isset($_SESSION['id']) || $this->currentUser() === null) {
            // Mémorise la page demandée (ex. lien d'un e-mail de rappel) pour y revenir
            // après la connexion. Seuls les chemins internes sont acceptés.
            $target = $_SERVER['REQUEST_URI'] ?? '';
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && self::isInternalPath($target)) {
                $_SESSION['after_login'] = $target;
            }
            $this->redirect('/connexion');
        }
    }

    protected function requireAuthApi(): void
    {
        if (!isset($_SESSION['id'])) {
            $this->json(['error' => 'Session expirée. Veuillez vous reconnecter.', 'code' => 'unauthenticated'], 401);
        }
    }

    protected function requireGuest(): void
    {
        if (isset($_SESSION['id'])) {
            $this->redirect('/calendrier');
        }
    }

    protected static function isInternalPath(string $path): bool
    {
        return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\');
    }

    /**
     * @param string $type 'error' | 'success' | 'info'
     */
    protected function flash(string $message, string $type = 'error'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    /**
     * @return array{message: string, type: string}|null
     */
    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        if (is_string($flash)) {
            // Format antérieur (chaîne seule), possible juste après une mise à jour
            return ['message' => $flash, 'type' => 'info'];
        }
        return is_array($flash) && isset($flash['message']) ? $flash : null;
    }

    /**
     * Conserve les valeurs saisies (jamais les mots de passe) pour pré-remplir le
     * formulaire après une redirection due à une erreur de validation.
     */
    protected function rememberInput(array $values): void
    {
        $_SESSION['old_input'] = $values;
    }

    protected function oldInput(): array
    {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
        return is_array($old) ? $old : [];
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
        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
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
            $this->json(['error' => 'Jeton de sécurité invalide. Veuillez recharger la page.', 'code' => 'csrf'], 403);
        }
    }

    /**
     * Limiteur de tentatives basé sur des fichiers temporaires (pas de table dédiée
     * nécessaire). Retourne false si la limite est dépassée pour la fenêtre de temps donnée.
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
