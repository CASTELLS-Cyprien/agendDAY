<?php
declare(strict_types=1);
namespace App\Models;

/**
 * Verrou nommé MySQL : empêche deux exécutions simultanées d'un même script cron.
 * Le verrou est libéré automatiquement si la connexion se ferme (script interrompu).
 */
class CronLock extends BaseModel
{
    public function acquire(string $name): bool
    {
        $stmt = $this->db()->prepare('SELECT GET_LOCK(:name, 0)');
        $stmt->execute([':name' => $name]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function release(string $name): void
    {
        $this->db()->prepare('SELECT RELEASE_LOCK(:name)')->execute([':name' => $name]);
    }
}
