<?php
declare(strict_types=1);
namespace Core;

/**
 * Point d'entrée commun des scripts de cron/ : autorisation et sortie.
 *
 * - En CLI (tâche planifiée OVH, crontab), aucun secret n'est demandé.
 * - En HTTP (service externe type cron-job.org), le secret CRON_SECRET doit être
 *   transmis dans l'en-tête « X-Cron-Secret ». Le paramètre ?secret= reste accepté
 *   pour ne pas casser une configuration existante, mais il apparaît dans les
 *   journaux d'accès : l'en-tête est à privilégier.
 */
final class Cron
{
    public static function authorize(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $secret = $_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['secret'] ?? '');

        if (!is_string($secret) || !defined('CRON_SECRET') || CRON_SECRET === ''
            || !hash_equals((string) CRON_SECRET, $secret)) {
            http_response_code(403);
            exit('Accès interdit.');
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');

        // Le service appelant peut couper la connexion avant la fin : le traitement
        // doit aller à son terme pour ne pas laisser d'envoi à moitié enregistré.
        ignore_user_abort(true);
        set_time_limit(120);
    }

    public static function finish(string $message): void
    {
        echo $message . PHP_EOL;
        exit(0);
    }
}
