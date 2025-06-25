<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Paris');

if (php_sapi_name() !== 'cli') {
    exit('Accès direct interdit.');
}

try {
    $bdd = new PDO("mysql:host=castelpcyp.mysql.db;dbname=castelpcyp", "castelpcyp", "reRPhrkHNNxoz69TfNKF", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    error_log("Erreur de connexion : " . $e->getMessage());
    exit();
}

// Récupérer l'heure actuelle
$now = new DateTime('now', new DateTimeZone('Europe/Paris'));
$now_str = $now->format('Y-m-d H:i:s');

error_log("Vérification des événements passés à : $now_str");

// Sélectionner les événements passés pour journalisation (optionnel)
$sql_select = "SELECT id, title, dateEvent, time FROM events WHERE CONCAT(dateEvent, ' ', time) < :now";
$requete_select = $bdd->prepare($sql_select);
$requete_select->execute([':now' => $now_str]);
$past_events = $requete_select->fetchAll(PDO::FETCH_ASSOC);

foreach ($past_events as $event) {
    error_log("Événement passé trouvé : ID {$event['id']}, Titre : {$event['title']}, Date : {$event['dateEvent']} {$event['time']}");
}

// Supprimer les événements passés
$sql_delete = "DELETE FROM events WHERE CONCAT(dateEvent, ' ', time) < :now";
$requete_delete = $bdd->prepare($sql_delete);
$requete_delete->execute([':now' => $now_str]);

$deleted_count = $requete_delete->rowCount();
error_log("Suppression terminée : $deleted_count événements supprimés à $now_str");

?>