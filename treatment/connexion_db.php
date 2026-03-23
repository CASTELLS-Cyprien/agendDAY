<?php
// Chemin absolu pour éviter les erreurs selon le fichier appelant
require_once __DIR__ . '/config.php';

function getDatabaseConnection(): PDO {
    try {
        $bdd = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $bdd;
    } catch (PDOException $e) {
        throw new PDOException("Erreur de connexion : " . $e->getMessage());
    }
}