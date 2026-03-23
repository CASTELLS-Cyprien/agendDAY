<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Détection automatique du chemin de config2.php
if (file_exists(__DIR__ . '/config2.php')) {
    require_once __DIR__ . '/config2.php';
} elseif (file_exists(__DIR__ . '/../config2.php')) {
    require_once __DIR__ . '/../config2.php';
} else {
    die('ERREUR : config2.php introuvable. Cherché dans : ' . __DIR__ . '/config2.php et ' . __DIR__ . '/../config2.php');
}

// Détection automatique du chemin de email_connexion.php
if (file_exists(__DIR__ . '/email_connexion.php')) {
    require_once __DIR__ . '/email_connexion.php';
} else {
    die('ERREUR : email_connexion.php introuvable dans : ' . __DIR__);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['message'] = "Erreur : La requête n'est pas de type POST.";
    header("Location: /contact");
    exit();
}

// Vérification reCAPTCHA
if (empty($_POST['g-recaptcha-response'])) {
    $_SESSION['message'] = "Veuillez compléter le reCAPTCHA.";
    header("Location: /contact");
    exit();
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'secret' => SECRET_KEY,
        'response' => $_POST['g-recaptcha-response'],
        'remoteip' => $_SERVER['REMOTE_ADDR'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    error_log("Erreur cURL reCAPTCHA : $curlError (HTTP $httpCode)");
    $_SESSION['message'] = "Erreur reCAPTCHA. Veuillez réessayer.";
    header("Location: /contact");
    exit();
}

$result = json_decode($response, true);
if (!isset($result['success']) || $result['success'] !== true) {
    $codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'inconnu';
    error_log("reCAPTCHA échoué : $codes");
    $_SESSION['message'] = "Vérification reCAPTCHA échouée. Veuillez réessayer.";
    header("Location: /contact");
    exit();
}

// Validation des champs
if (empty($_POST['nom']) || empty($_POST['email']) || empty($_POST['message'])) {
    $_SESSION['message'] = "Veuillez remplir tous les champs.";
    header("Location: /contact");
    exit();
}

$nom = htmlspecialchars(trim($_POST['nom']), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Adresse email invalide.";
    header("Location: /contact");
    exit();
}

// Envoi de l'email
try {
    $mail = getEmailConnection();
    $mail->addAddress(EMAIL_FROM);
    $mail->Subject = '=?UTF-8?B?' . base64_encode("Nouveau message de contact de $nom") . '?=';

    $mail->Body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #f8fafc; border-radius: 8px; overflow: hidden; }
        .header { background: #485fc7; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 20px; color: #333; line-height: 1.6; }
        .details { background: #f9f9f9; padding: 15px; border-radius: 4px; }
        .cta-button { display: inline-block; padding: 12px 20px; background: #485fc7; color: #fff !important; text-decoration: none; border-radius: 4px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'><h1>Nouveau message de contact</h1></div>
        <div class='content'>
            <div class='details'>
                <p><strong>Nom :</strong> {$nom}</p>
                <p><strong>Email :</strong> {$email}</p>
                <p><strong>Message :</strong><br>" . nl2br($message) . "</p>
            </div>
            <p><a href='mailto:{$email}' class='cta-button'>Répondre</a></p>
        </div>
    </div>
</body>
</html>";

    $mail->AltBody = "Nom : {$nom}\nEmail : {$email}\nMessage :\n{$message}";
    $mail->send();

    $_SESSION['message'] = "Votre message a été envoyé avec succès !";
} catch (Exception $e) {
    error_log("Erreur envoi contact : " . $e->getMessage());
    $_SESSION['message'] = "Erreur lors de l'envoi : " . $e->getMessage();
}

header("Location: /contact");
exit();