<?php
declare(strict_types=1);

use App\Models\BaseModel;

/**
 * Gestion des bases de test. Garde-fou : refuse toute base qui n'est pas locale
 * et dont le nom ne se termine pas par « _test » (aucun risque d'écraser une
 * base réelle, même avec une configuration erronée).
 */
final class TestDatabase
{
    private static ?string $password = null;

    /**
     * Mot de passe des comptes de test, tiré au hasard à chaque exécution
     * (aucune valeur en dur que les scanners de secrets signaleraient).
     */
    public static function password(): string
    {
        return self::$password ??= bin2hex(random_bytes(12));
    }

    public static function server(): PDO
    {
        if (!in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('Les tests ne s\'exécutent que sur un serveur MySQL local.');
        }
        return new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function available(): bool
    {
        try {
            self::server();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Recrée la base $name et y exécute les fichiers SQL donnés.
     */
    public static function recreate(string $name, array $sqlFiles): PDO
    {
        if (!preg_match('/^[a-z0-9_]+_test$/', $name)) {
            throw new RuntimeException("Nom de base de test refusé : {$name}");
        }

        $pdo = self::server();
        $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");

        foreach ($sqlFiles as $file) {
            self::runSql($pdo, (string) file_get_contents($file));
        }

        BaseModel::resetConnection();
        return $pdo;
    }

    /** Base principale des tests (schéma complet). */
    public static function reset(): PDO
    {
        return self::recreate(DB_NAME, [APP_ROOT . '/database/schema.sql']);
    }

    public static function connect(): PDO
    {
        $pdo = self::server();
        $pdo->exec('USE `' . DB_NAME . '`');
        return $pdo;
    }

    public static function runSql(PDO $pdo, string $sql): void
    {
        $sql = (string) preg_replace('/--\s.*$/m', '', $sql);
        foreach (preg_split('/;\s*(?:\R|$)/', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function createUser(
        PDO $pdo,
        string $email,
        string $name = 'Utilisateur Test',
        bool $emailReminders = true
    ): int {
        static $hash = null;
        $hash ??= password_hash(self::password(), PASSWORD_BCRYPT, ['cost' => 4]);

        $stmt = $pdo->prepare(
            'INSERT INTO users (nomUtilisateur, email, motDePasse, is_confirmed, email_reminders)
             VALUES (:name, :email, :hash, 1, :reminders)'
        );
        $stmt->execute([':name' => $name, ':email' => $email, ':hash' => $hash, ':reminders' => $emailReminders ? 1 : 0]);
        return (int) $pdo->lastInsertId();
    }
}
