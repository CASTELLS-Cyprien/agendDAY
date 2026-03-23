<?php
// ============================================================
// SEND_REMINDERS.PHP — Appelé toutes les 5 min par cron-job.org
// URL : https://agendday.castells-cyprien.ovh/send_reminders.php?secret=CRON_SECRET
// ============================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/treatment/email_connexion.php';
require_once __DIR__ . '/treatment/connexion_db.php';

// --- Sécurité : vérification du token ---
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals(CRON_SECRET, $secret)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
}

// --- Connexion BDD ---
try {
    $bdd = getDatabaseConnection();
} catch (PDOException $e) {
    error_log("[cron] Erreur DB : " . $e->getMessage());
    http_response_code(500);
    exit('Erreur DB');
}

// --- Recherche des événements à rappeler ---
// Fenêtre : événements dans les 5 prochaines minutes (correspond à la fréquence du cron)
$now = new DateTime('now', new DateTimeZone('Europe/Paris'));
$start_time = $now->format('Y-m-d H:i:s');
$end_time = (clone $now)->modify('+5 minutes')->format('Y-m-d H:i:s');

$stmt = $bdd->prepare(
    "SELECT e.id, e.title, e.dateEvent, e.time, e.descriptionEvent,
            u.email, u.nomUtilisateur
     FROM events e
     JOIN users u ON e.userID = u.id
     WHERE CONCAT(e.dateEvent, ' ', e.time) BETWEEN :start AND :end
       AND e.dateEvent >= CURDATE()
       AND (e.sentReminder IS NULL OR e.sentReminder = FALSE)"
);
$stmt->execute([':start' => $start_time, ':end' => $end_time]);
$events = $stmt->fetchAll();

if (empty($events)) {
    echo "OK — 0 rappel à envoyer.";
    exit;
}

$sent = 0;
$fails = 0;

foreach ($events as $event) {
    // Validation email
    if (!filter_var($event['email'], FILTER_VALIDATE_EMAIL)) {
        error_log("[cron] Email invalide — ID {$event['id']}");
        continue;
    }

    // Verrou anti-doublon (évite double envoi si cron-job.org relance)
    $lock_file = sys_get_temp_dir() . "/reminder_{$event['id']}.lock";
    if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 600) {
        continue;
    }
    touch($lock_file);

    // Calcul temps restant
    $eventDT = new DateTime($event['dateEvent'] . ' ' . $event['time'], new DateTimeZone('Europe/Paris'));
    $diff = $now->diff($eventDT);
    $tempsRestant = $diff->h > 0 ? "dans {$diff->h}h{$diff->i}min" : "dans {$diff->i} minute(s)";

    $desc = !empty($event['descriptionEvent'])
        ? "<p><strong>Description :</strong> " . nl2br(htmlspecialchars($event['descriptionEvent'])) . "</p>"
        : '';

    $success = false;
    for ($attempt = 1; $attempt <= 3 && !$success; $attempt++) {
        try {
            $mail = getEmailConnection();
            $mail->addAddress($event['email']);
            $mail->Subject = '=?UTF-8?B?' . base64_encode("⏰ Rappel : " . $event['title']) . '?=';

            $mail->Body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
        .container{max-width:600px;margin:20px auto;background:#f8fafc;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,.1)}
        .header{background:#485fc7;color:#fff;padding:20px;text-align:center}
        .header h1{margin:0;font-size:24px}
        .badge{display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:13px;margin-top:8px}
        .content{padding:20px;color:#333;line-height:1.6}
        .details{background:#f0f4ff;border-left:4px solid #485fc7;padding:15px;border-radius:0 4px 4px 0;margin:15px 0}
        .details p{margin:5px 0}
        .cta{display:inline-block;padding:12px 24px;background:#485fc7;color:#fff!important;text-decoration:none;border-radius:4px;font-weight:bold;margin-top:10px}
        .footer{text-align:center;font-size:12px;color:#aaa;padding:15px}
    </style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1> Rappel d'événement</h1>
        <div class='badge'>" . htmlspecialchars($tempsRestant) . "</div>
    </div>
    <div class='content'>
        <p>Bonjour <strong>" . htmlspecialchars($event['nomUtilisateur']) . "</strong>,</p>
        <p>Votre événement approche :</p>
        <div class='details'>
            <p> <strong>" . htmlspecialchars($event['title']) . "</strong></p>
            <p> " . date('d/m/Y', strtotime($event['dateEvent'])) . " à " . substr($event['time'], 0, 5) . "</p>
            {$desc}
        </div>
        <a href='https://agendday.castells-cyprien.ovh/calendrier' class='cta'>Voir mon calendrier</a>
    </div>
    <div class='footer'>AgendDAY · <a href='https://agendday.castells-cyprien.ovh' style='color:#aaa'>agendday.castells-cyprien.ovh</a></div>
</div>
</body>
</html>";

            $mail->AltBody = "Bonjour {$event['nomUtilisateur']},\n\n"
                . "Rappel ({$tempsRestant}) : {$event['title']}\n"
                . "Le " . date('d/m/Y', strtotime($event['dateEvent'])) . " à " . substr($event['time'], 0, 5) . "\n"
                . (!empty($event['descriptionEvent']) ? "Description : {$event['descriptionEvent']}\n" : '')
                . "\nhttps://agendday.castells-cyprien.ovh/calendrier\n\nAgendDAY";

            $mail->send();

            $bdd->prepare("UPDATE events SET sentReminder = TRUE WHERE id = :id")
                ->execute([':id' => $event['id']]);

            $sent++;
            $success = true;
            error_log("[cron] ✓ Rappel envoyé — ID {$event['id']} à {$event['email']}");

        } catch (Exception $e) {
            error_log("[cron] Tentative {$attempt} échouée — ID {$event['id']} : " . $e->getMessage());
            if ($attempt < 3)
                sleep(2);
        }
    }

    if (!$success) {
        $fails++;
        error_log("[cron] ✗ Échec définitif — ID {$event['id']}");
    }

    if (file_exists($lock_file))
        unlink($lock_file);
}

$msg = "OK — {$sent} rappel(s) envoyé(s), {$fails} échec(s) — " . $now->format('d/m/Y H:i:s');
error_log("[cron] $msg");
echo $msg;