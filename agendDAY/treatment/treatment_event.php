<?php
session_start();

require 'connexion_db.php';

try {
    $bdd = getDatabaseConnection();
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(["error" => "Erreur de connexion à la base de données."]);
    exit;
}

// Vérifier si c'est une requête POST pour ajouter un événement
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["addEvent"])) {
    if (!isset($_SESSION['id'])) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Vous devez être connecté pour ajouter un événement."]);
        exit;
    }

    if (empty($_POST["title"]) || empty($_POST["time"]) || empty($_POST["date"])) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Les champs titre, heure et date doivent être remplis."]);
        exit;
    }

    $title = htmlspecialchars($_POST["title"]);
    $time = htmlspecialchars($_POST["time"]);
    $description = isset($_POST["descriptionEvent"]) ? htmlspecialchars($_POST["descriptionEvent"]) : null;
    $date = htmlspecialchars($_POST["date"]);
    $userID = $_SESSION['id'];

    try {
        $sql = "INSERT INTO events (title, time, descriptionEvent, userID, dateEvent) 
                VALUES (:title, :time, :description, :userID, :dateEvent)";
        $requete = $bdd->prepare($sql);
        $requete->execute([
            ":title" => $title,
            ":time" => $time,
            ":description" => $description,
            ":userID" => $userID,
            ":dateEvent" => $date,
        ]);

        // Redirection si c'est un ajout manuel, sinon réponse JSON
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header("Location: ../calendar.php?date=" . $date);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(["success" => true]);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error adding event: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(["error" => "Erreur lors de l'ajout de l'événement."]);
        exit;
    }
}

// Requête POST pour récupérer les événements d'une date précise
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["date"]) && !isset($_POST["addEvent"])) {
    if (!isset($_SESSION['id'])) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Session expirée"]);
        exit;
    }

    $date = $_POST["date"];
    $userID = $_SESSION['id'];

    try {
        $stmt = $bdd->prepare("SELECT id, title, time, descriptionEvent FROM events WHERE dateEvent = ? AND userID = ? ORDER BY time ASC");
        $stmt->execute([$date, $userID]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header("Content-Type: application/json");
        echo json_encode($events);
        exit();
    } catch (PDOException $e) {
        error_log("Error fetching events for date: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(["error" => "Erreur lors de la récupération des événements."]);
        exit;
    }
}

// Requête GET pour récupérer tous les événements (affichage calendrier)
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (!isset($_SESSION['id'])) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $userID = $_SESSION['id'];

    try {
        $stmt = $bdd->prepare("
            SELECT 
                title, time, descriptionEvent,
                DAY(dateEvent) AS day,
                MONTH(dateEvent) AS month,
                YEAR(dateEvent) AS year
            FROM events
            WHERE userID = ?
        ");
        $stmt->execute([$userID]);
        $allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header("Content-Type: application/json");
        echo json_encode($allEvents);
        exit();
    } catch (PDOException $e) {
        error_log("Error fetching all events: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(["error" => "Erreur lors de la récupération des événements."]);
        exit;
    }
}

// Si aucune condition ne correspond
http_response_code(400);
header('Content-Type: application/json');
echo json_encode(["error" => "Requête invalide."]);
?>