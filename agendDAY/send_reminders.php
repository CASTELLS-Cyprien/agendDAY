<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'treatment/email_connexion.php';
require_once 'treatment/connexion_db.php';

date_default_timezone_set('Europe/Paris');

// Vérification accès HTTP
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD']) || !isset($_GET['secret']) || $_GET['secret'] !== 'reRPhrkHNNxoz69TfNKF') {
    error_log("403 Forbidden: Clé secrète incorrecte ou requête invalide. Reçu: " . ($_GET['secret'] ?? 'aucune clé'));
    http_response_code(403);
    exit('Accès interdit.');
}

// Configuration des plages horaires
$time_ranges = [
    ['start' => -5, 'end' => 0, 'label' => '-5-0 min'], // Rattrapage
    ['start' => 0, 'end' => 6, 'label' => '0-6 min'],
    ['start' => 5, 'end' => 11, 'label' => '5-11 min'],
    ['start' => 10, 'end' => 16, 'label' => '10-16 min'],
];

// Connexion DB
try {
    $bdd = getDatabaseConnection();
} catch (PDOException $e) {
    error_log("Erreur DB : " . $e->getMessage());
    exit('Erreur de connexion à la base de données.');
}

// Vérification des événements
$now = new DateTime();
foreach ($time_ranges as $range) {
    $start_time = (clone $now)->modify("+{$range['start']} minutes")->format('Y-m-d H:i:s');
    $end_time = (clone $now)->modify("+{$range['end']} minutes")->format('Y-m-d H:i:s');

    $sql = "SELECT e.*, u.email, u.nomUtilisateur 
            FROM events e 
            JOIN users u ON e.userID = u.id 
            WHERE CONCAT(e.dateEvent, ' ', e.time) BETWEEN :start AND :end 
            AND e.dateEvent >= CURDATE()
            AND (e.sentReminder IS NULL OR e.sentReminder = FALSE)";
    $requete = $bdd->prepare($sql);
    $requete->execute([':start' => $start_time, ':end' => $end_time]);
    $events = $requete->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        // Valider l'email
        if (!filter_var($event['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Email non valide pour l'événement ID {$event['id']}: {$event['email']}");
            continue;
        }

        // Verrouillage par ID d'événement
        $lock_file = "/tmp/send_reminders2_event_{$event['id']}.lock";
        if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 3600) {
            error_log("Événement ID {$event['id']} déjà en traitement (verrouillage).");
            continue;
        }
        touch($lock_file);

        $attempt = 1;
        $max_attempts = 3;
        $success = false;
        while ($attempt <= $max_attempts && !$success) {
            try {
                // Configurer l'email avec PHPMailer via email_connexion.php
                $mail = getEmailConnection();
                $mail->addAddress($event['email']);
                $mail->Subject = '=?UTF-8?B?' . base64_encode("Rappel : Événement \"" . htmlspecialchars($event['title']) . "\" à venir") . '?=';

                // Corps HTML de l'email
                $body_html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: #f8fafc; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background-color: #485fc7; color: #ffffff; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; color: #333333; }
        .content p { margin: 10px 0; line-height: 1.6; }
        .event-details { background-color: #f9f9f9; padding: 15px; border-radius: 4px; }
        .event-details p { margin: 5px 0; }
        .cta-button { display: inline-block; padding: 12px 20px; background-color: #485fc7; text-decoration: none; border-radius: 4px; margin: 15px 0; border: 1px solid #485fc7; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Rappel d'événement</h1>
        </div>
        <div class='content'>
            <p>Bonjour " . htmlspecialchars($event['nomUtilisateur']) . ",</p>
            <p>Un rappel pour votre événement à venir :</p>
            <div class='event-details'>
                <p><strong>Titre :</strong> " . htmlspecialchars($event['title']) . "</p>
                <p><strong>Date :</strong> {$event['dateEvent']}</p>
                <p><strong>Heure :</strong> {$event['time']}</p>";
                if (!empty($event['descriptionEvent'])) {
                    $body_html .= "<p><strong>Description :</strong> " . nl2br(htmlspecialchars($event['descriptionEvent'])) . "</p>";
                }
                $body_html .= "</div>
            <p><a href='https://castells-cyprien.ovh/agendDAY/calendar.php' class='cta-button' style='color: #ffffff !important; background-color: #485fc7; text-decoration: none; padding: 12px 20px; border-radius: 4px; display: inline-block;'>Gérer vos événements</a></p>
        </div>
    </div>
</body>
</html>";
                $mail->Body = $body_html;

                // Corps texte brut
                $mail->AltBody = "Bonjour {$event['nomUtilisateur']},\n\nRappel pour votre événement :\nTitre : {$event['title']}\nDate : {$event['dateEvent']}\nHeure : {$event['time']}";
                if (!empty($event['descriptionEvent'])) {
                    $mail->AltBody .= "\nDescription : {$event['descriptionEvent']}";
                }
                $mail->AltBody .= "\n\nGérez vos événements : https://castells-cyprien.ovh/agendDAY/calendar.php\n\nCordialement,\nL'équipe agendDAY";

                // Envoyer l'email
                $mail->send();
                $success = true;

                // Mettre à jour l'état du rappel
                $update_sql = "UPDATE events SET sentReminder = TRUE WHERE id = :eventID";
                $bdd->prepare($update_sql)->execute([':eventID' => $event['id']]);
                error_log("Rappel envoyé pour l'événement ID {$event['id']} à {$event['email']}");
            } catch (Exception $e) {
                error_log("Échec tentative $attempt pour l'événement ID {$event['id']}: " . $e->getMessage());
                $attempt++;
                sleep(5);
            }
        }

        if (!$success) {
            error_log("Échec définitif pour l'événement ID {$event['id']} après $max_attempts tentatives");
        }
        unlink($lock_file);
    }
}

echo "Rappels envoyés.";
?>