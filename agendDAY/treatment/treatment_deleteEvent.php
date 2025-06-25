<?php
session_start();

require 'connexion_db.php';

try {
    $bdd = getDatabaseConnection();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Erreur de connexion : " . $e->getMessage()]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["deleteEvent"])) {
    if (!isset($_SESSION['id'])) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Vous devez être connecté pour supprimer un événement."]);
        exit;
    }

    if (empty($_POST["eventID"])) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "ID de l'événement manquant."]);
        exit;
    }

    $eventID = intval($_POST["eventID"]);
    $userID = $_SESSION['id'];

    try {
        // Vérifier que l'événement appartient bien à l'utilisateur
        $checkStmt = $bdd->prepare("SELECT id FROM events WHERE id = ? AND userID = ?");
        $checkStmt->execute([$eventID, $userID]);
        
        if ($checkStmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode(["error" => "Événement non trouvé ou non autorisé."]);
            exit;
        }

        // Supprimer l'événement
        $deleteStmt = $bdd->prepare("DELETE FROM events WHERE id = ? AND userID = ?");
        $deleteStmt->execute([$eventID, $userID]);

        header('Content-Type: application/json');
        echo json_encode(["success" => true]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Erreur lors de la suppression : " . $e->getMessage()]);
        exit;
    }
}

// Si aucune condition ne correspond
http_response_code(400);
header('Content-Type: application/json');
echo json_encode(["error" => "Requête invalide."]);
?>