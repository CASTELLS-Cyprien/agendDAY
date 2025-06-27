<?php
// Activer l'affichage des erreurs pour le débogage (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'extension cURL est disponible
if (!function_exists('curl_init')) {
    die('Erreur : L\'extension cURL n\'est pas activée sur ce serveur.');
}

require_once 'email_connexion.php';
session_start();

// Vil Vérifier si le formulaire a été soumis correctement
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifier la réponse reCAPTCHA
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        $captcha = $_POST['g-recaptcha-response'];
        $secretKey = "6LdxhW8rAAAAAMiLn5NjrXIGyDJ__0zguLWmkfBm";
        $url = 'https://www.google.com/recaptcha/api/siteverify';

        // Utiliser cURL pour la requête
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secretKey,
            'response' => $captcha,
            'remoteip' => $_SERVER['REMOTE_ADDR'] // Ajout de l'IP pour une meilleure vérification
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 secondes
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Erreur cURL reCAPTCHA : $error");
            $_SESSION['message'] = "Erreur reCAPTCHA : Échec de la connexion à l'API. " . $error;
            header("Location: ../contact.php");
            exit();
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Erreur reCAPTCHA : Code HTTP $httpCode");
            $_SESSION['message'] = "Erreur reCAPTCHA : Réponse non valide de l'API (HTTP $httpCode).";
            header("Location: ../contact.php");
            exit();
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['success'])) {
            error_log("Erreur reCAPTCHA : Réponse invalide - " . json_encode($response));
            $_SESSION['message'] = "Erreur reCAPTCHA : Réponse de l'API non valide.";
            header("Location: ../contact.php");
            exit();
        }

        if ($result['success'] !== true) {
            $errorCodes = isset($result['error-codes']) ? implode(", ", $result['error-codes']) : "Erreur inconnue";
            error_log("Erreur reCAPTCHA : $errorCodes");
            $_SESSION['message'] = "Erreur reCAPTCHA : $errorCodes";
            header("Location: ../contact.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Erreur : Veuillez compléter le reCAPTCHA.";
        header("Location: ../contact.php");
        exit();
    }

    // Vérifier si tous les champs requis sont présents
    if (isset($_POST["nom"]) && isset($_POST["email"]) && isset($_POST["message"])) {
        // Nettoyer les données
        $nom = htmlspecialchars(trim($_POST["nom"]), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars(trim($_POST["email"]), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars(trim($_POST["message"]), ENT_QUOTES, 'UTF-8');

        // Valider l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = "Erreur : Adresse email invalide.";
            header("Location: ../contact.php");
            exit();
        }

        try {
            // Configurer l'email avec PHPMailer
            $mail = getEmailConnection();
            $mail->addAddress(EMAIL_FROM);
            $mail->Subject = "Nouveau message de contact de $nom";

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
        .contact-details { background-color: #f9f9f9; padding: 15px; border-radius: 4px; }
        .contact-details p { margin: 5px 0; }
        .cta-button { display: inline-block; padding: 12px 20px; background-color: #485fc7; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 15px 0; border: 1px solid #485fc7; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Nouveau message de contact</h1>
        </div>
        <div class='content'>
            <p>Bonjour,</p>
            <p>Vous avez reçu un nouveau message via le formulaire de contact d'AgendDAY :</p>
            <div class='contact-details'>
                <p><strong>Nom :</strong> $nom</p>
                <p><strong>Email :</strong> $email</p>
                <p><strong>Message :</strong><br>" . nl2br($message) . "</p>
            </div>
            <p><a href='mailto:$email' class='cta-button'>Répondre à cet email</a></p>
        </div>
    </div>
</body>
</html>";
            $mail->Body = $body_html;

            // Corps texte brut
            $mail->AltBody = "Bonjour,\n\nVous avez reçu un nouveau message via le formulaire de contact d'AgendDAY :\n\nNom : $nom\nEmail : $email\nMessage :\n$message";

            // Envoyer l'email
            if ($mail->send()) {
                // Rediriger vers contact.php avec un message de confirmation
                $_SESSION['message'] = "Votre message a été envoyé avec succès !";
                header("Location: ../contact.php");
                exit();
            } else {
                error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
                $_SESSION['message'] = "Erreur : Échec de l'envoi de l'email. Veuillez réessayer plus tard.";
                header("Location: ../contact.php");
                exit();
            }
        } catch (Exception $e) {
            error_log("Exception PHPMailer : " . $e->getMessage());
            $_SESSION['message'] = "Erreur : Échec de l'envoi de l'email. " . $e->getMessage();
            header("Location: ../contact.php");
            exit();
        }
    } else {
        // Afficher quels champs manquent pour le débogage
        $missing = [];
        if (!isset($_POST["nom"]))
            $missing[] = "nom";
        if (!isset($_POST["email"]))
            $missing[] = "email";
        if (!isset($_POST["message"]))
            $missing[] = "message";
        $_SESSION['message'] = "Erreur : Les champs suivants sont manquants : " . implode(", ", $missing);
        header("Location: ../contact.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Erreur : La requête n'est pas de type POST.";
    header("Location: ../contact.php");
    exit();
}
?>