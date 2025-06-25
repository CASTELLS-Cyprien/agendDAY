<?php
require_once 'config.php';

function getDatabaseConnection() {
    try {
        $bdd = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $bdd;
    } catch (PDOException $e) {
        throw new PDOException("Erreur de connexion : " . $e->getMessage());
    }
}
?>