<?php
// ===== TRAITEMENT DES ÉVÉNEMENTS - VERSION OPTIMISÉE =====

session_start();

// Configuration
define('MAX_TITLE_LENGTH', 200);
define('MAX_DESCRIPTION_LENGTH', 2000);

// Headers JSON par défaut
header('Content-Type: application/json');

// Vérification session utilisateur
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
}

// Connexion BDD
try {
    require 'connexion_db.php';
    $bdd = getDatabaseConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données.']);
    exit;
}

$userID = $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];

// === MISE À JOUR D'UN ÉVÉNEMENT ===
if ($method === 'POST' && isset($_POST['updateEvent'], $_POST['eventId'])) {
    updateEvent($bdd, $userID);
}

// === AJOUT D'UN ÉVÉNEMENT ===
if ($method === 'POST' && isset($_POST['addEvent'])) {
    addEvent($bdd, $userID);
}

// === RÉCUPÉRATION ÉVÉNEMENTS D'UNE DATE ===
if ($method === 'POST' && isset($_POST['date']) && !isset($_POST['addEvent'], $_POST['updateEvent'])) {
    getEventsByDate($bdd, $userID, $_POST['date']);
}

// === RÉCUPÉRATION TOUS LES ÉVÉNEMENTS ===
if ($method === 'GET') {
    getAllEvents($bdd, $userID);
}

// Si aucune condition ne correspond
http_response_code(400);
echo json_encode(['error' => 'Requête invalide.']);
exit;

// ============================================
// FONCTIONS
// ============================================

/**
 * Valide les données d'un événement
 */
function validateEventData($title, $time, $date, $description = '')
{
    $errors = [];

    if (empty($title) || strlen(trim($title)) === 0) {
        $errors[] = 'Le titre est requis.';
    } elseif (strlen($title) > MAX_TITLE_LENGTH) {
        $errors[] = 'Le titre est trop long (' . MAX_TITLE_LENGTH . ' caractères max).';
    }

    if (empty($time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $time)) {
        $errors[] = 'Heure invalide (format HH:MM requis).';
    }

    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Date invalide (format YYYY-MM-DD requis).';
    }

    if (strlen($description) > MAX_DESCRIPTION_LENGTH) {
        $errors[] = 'La description est trop longue (' . MAX_DESCRIPTION_LENGTH . ' caractères max).';
    }

    return $errors;
}

/**
 * Nettoie et sécurise les données
 */
function sanitizeData($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Ajoute un événement
 */
function addEvent($bdd, $userID)
{
    $title = $_POST['title'] ?? '';
    $time = $_POST['time'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['descriptionEvent'] ?? '';

    // Validation
    $errors = validateEventData($title, $time, $date, $description);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    // Nettoyage
    $title = sanitizeData($title);
    $time = sanitizeData($time);
    $date = sanitizeData($date);
    $description = sanitizeData($description);

    try {
        // Assurez-vous d'initialiser sentReminder à 0
        $stmt = $bdd->prepare(
            "INSERT INTO events (title, time, descriptionEvent, userID, dateEvent, sentReminder) 
     VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([$title, $time, $description, $userID, $date]);

        echo json_encode([
            'success' => true,
            'id' => $bdd->lastInsertId(),
            'message' => 'Événement ajouté avec succès.'
        ]);
        exit;
    } catch (PDOException $e) {
        error_log("Error adding event: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de l\'ajout de l\'événement.']);
        exit;
    }
}

/**
 * Met à jour un événement
 */
function updateEvent($bdd, $userID)
{
    $eventId = intval($_POST['eventId']);
    $title = $_POST['title'] ?? '';
    $time = $_POST['time'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['descriptionEvent'] ?? '';

    // Validation
    $errors = validateEventData($title, $time, $date, $description);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    // Nettoyage
    $title = sanitizeData($title);
    $time = sanitizeData($time);
    $date = sanitizeData($date);
    $description = sanitizeData($description);

    try {
        // Vérifier que l'événement appartient à l'utilisateur
        $checkStmt = $bdd->prepare("SELECT id FROM events WHERE id = ? AND userID = ?");
        $checkStmt->execute([$eventId, $userID]);

        if (!$checkStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Événement introuvable ou accès non autorisé.']);
            exit;
        }


        $stmt = $bdd->prepare(
            "UPDATE events 
            SET title = ?, time = ?, descriptionEvent = ?, dateEvent = ?, sentReminder = 0 
            WHERE id = ? AND userID = ?"
        );
        $stmt->execute([$title, $time, $description, $date, $eventId, $userID]);

        echo json_encode([
            'success' => true,
            'message' => 'Événement modifié avec succès.'
        ]);
        exit;
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour.']);
        exit;
    }
}

/**
 * Récupère les événements d'une date spécifique
 */
function getEventsByDate($bdd, $userID, $date)
{
    // Validation de la date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Format de date invalide.']);
        exit;
    }

    try {
        $stmt = $bdd->prepare(
            "SELECT id, title, time, descriptionEvent 
             FROM events 
             WHERE dateEvent = ? AND userID = ? 
             ORDER BY time ASC"
        );
        $stmt->execute([$date, $userID]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($events);
        exit;
    } catch (PDOException $e) {
        error_log("Error fetching events for date: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la récupération des événements.']);
        exit;
    }
}

/**
 * Récupère tous les événements de l'utilisateur
 */
function getAllEvents($bdd, $userID)
{
    try {
        $stmt = $bdd->prepare(
            "SELECT 
                title, 
                time, 
                descriptionEvent,
                DAY(dateEvent) AS day,
                MONTH(dateEvent) AS month,
                YEAR(dateEvent) AS year
             FROM events
             WHERE userID = ?
             ORDER BY dateEvent ASC, time ASC"
        );
        $stmt->execute([$userID]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($events);
        exit;
    } catch (PDOException $e) {
        error_log("Error fetching all events: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la récupération des événements.']);
        exit;
    }
}