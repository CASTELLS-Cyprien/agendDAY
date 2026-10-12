<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

abstract class BaseModel
{
    private static ?PDO $instance = null;

    protected function db(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            // Aligne NOW()/CURRENT_TIMESTAMP de MySQL sur le fuseau de PHP (APP_TIMEZONE) :
            // les dates écrites par PHP et celles comparées par MySQL restent cohérentes,
            // quel que soit le fuseau configuré sur le serveur MySQL. Le format « P »
            // (ex. +02:00) ne contient que des caractères sûrs.
            self::$instance->exec("SET time_zone = '" . (new \DateTimeImmutable())->format('P') . "'");
        }
        return self::$instance;
    }

    /**
     * Exécute $callback dans une transaction ; annule tout en cas d'exception.
     * Les modèles partagent la même connexion : une transaction ouverte ici couvre
     * aussi les requêtes des autres modèles appelées dans le callback.
     */
    public function transaction(callable $callback): mixed
    {
        $db = $this->db();
        if ($db->inTransaction()) {
            return $callback();
        }

        $db->beginTransaction();
        try {
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Réservé aux tests et aux scripts : réinitialise la connexion partagée.
     */
    public static function resetConnection(): void
    {
        self::$instance = null;
    }
}
