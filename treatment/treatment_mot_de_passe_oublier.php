<?php
session_start();

require_once __DIR__ . '/email_connexion.php';
require_once __DIR__ . '/connexion_db.php';

if (!isset($_POST['email'], $_POST['renitialiserMotDePasse'])) {
    $_SESSION['message'] = "Formulaire invalide.";
    header("Location: /mot-de-passe-oublie");
    exit();
}

try {
    $bdd = getDatabaseConnection();
    $email = trim($_POST['email']);

    // Récupérer l'utilisateur (nécessaire pour son nom dans l'email)
    $stmt = $bdd->prepare("SELECT id, nomUtilisateur FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Message neutre pour ne pas divulguer l'existence du compte
        $_SESSION['message'] = "Si cette adresse est associée à un compte, un email vous a été envoyé.";
        header("Location: /mot-de-passe-oublie");
        exit();
    }

    // Générer et enregistrer le token (valide 1 heure)
    $token = bin2hex(random_bytes(50));
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $bdd->prepare(
        "UPDATE users SET reset_token = :token, reset_token_expiration = :expiration WHERE email = :email"
    );
    $stmt->execute([':token' => $token, ':expiration' => $expiration, ':email' => $email]);

    // Envoi de l'email
    $reset_link = 'https://agendday.castells-cyprien.ovh/treatment/reset_password.php?token=' . urlencode($token);

    $mail = getEmailConnection();
    $mail->addAddress($email);
    $mail->Subject = '=?UTF-8?B?' . base64_encode('Réinitialisation de votre mot de passe - agendDAY') . '?=';

    $mail->Body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: #f8fafc; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .header { background: #485fc7; color: #fff; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; color: #333; line-height: 1.6; }
        .cta-button { display: inline-block; padding: 12px 20px; background: #485fc7; color: #fff !important; text-decoration: none; border-radius: 4px; margin: 15px 0; }
        .footer { background: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'><h1>Réinitialisation du mot de passe</h1></div>
        <div class='content'>
            <p>Bonjour " . htmlspecialchars($user['nomUtilisateur']) . ",</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe AgendDAY.</p>
            <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
            <p><a href='" . $reset_link . "' class='cta-button'>Réinitialiser mon mot de passe</a></p>
            <p>Ou copiez ce lien dans votre navigateur :<br>
               <a href='" . $reset_link . "' style='color:#485fc7;'>" . $reset_link . "</a></p>
            <p><small>Ce lien est valable <strong>1 heure</strong>. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</small></p>
        </div>
        <div class='footer'>Cordialement,<br>L'équipe AgendDAY</div>
    </div>
</body>
</html>";

    $mail->AltBody = "Bonjour {$user['nomUtilisateur']},\n\n"
        . "Lien de réinitialisation (valable 1 heure) :\n$reset_link\n\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\nL'équipe AgendDAY";

    $mail->send();
    $_SESSION['message'] = "Si cette adresse est associée à un compte, un email vous a été envoyé.";

} catch (Exception $e) {
    error_log("Erreur envoi email reset : " . $e->getMessage());
    $_SESSION['message'] = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
} catch (PDOException $e) {
    error_log("Erreur DB reset : " . $e->getMessage());
    $_SESSION['message'] = "Erreur base de données. Veuillez réessayer.";
}

header("Location: /mot-de-passe-oublie");
exit();