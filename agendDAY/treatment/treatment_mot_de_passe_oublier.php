<?php
session_start();

require_once 'email_connexion.php';
require_once 'connexion_db.php';

if (isset($_POST['email']) && isset($_POST['renitialiserMotDePasse'])) {
    try {
        $bdd = getDatabaseConnection();

        $email = $_POST['email'];

        // Vérifie si l'email existe
        $stmt = $bdd->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $token = bin2hex(random_bytes(50));
            $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Enregistre le token
            $stmt = $bdd->prepare("UPDATE users SET reset_token = :token, reset_token_expiration = :expiration WHERE email = :email");
            $stmt->execute([
                ':token' => $token,
                ':expiration' => $expiration,
                ':email' => $email
            ]);

            // Envoie du mail
            try {
                $mail = getEmailConnection();
                $mail->addAddress($email);
                $mail->Subject = 'Réinitialisation de votre mot de passe - agendDAY';

                $reset_link = 'https://castells-cyprien.ovh/agendDAY/treatment/reset_password.php?token=' . urlencode($token);

                // Préparation du HTML principal
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
        .cta-button { display: inline-block; padding: 12px 20px; background-color: #485fc7; text-decoration: none; border-radius: 4px; margin: 15px 0; border: 1px solid #485fc7; box-shadow: 0 2px 4px rgba(0,0,0,0.2); color: #ffffff !important; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Réinitialisation de votre mot de passe</h1>
        </div>
        <div class='content'>
            <p>Bonjour " . htmlspecialchars($user['nomUtilisateur']) . ",</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte agendDAY.</p>
            <p>Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>
            <p><a href='" . $reset_link . "' class='cta-button'>Réinitialiser mon mot de passe</a></p>
            <p><small>Ce lien est valable pendant 1 heure.</small></p>
            <p>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>
            <br>
            <p style='font-size: 12px; color: #666;'>Cordialement,<br>L'équipe agendDAY</p>
        </div>
    </div>
</body>
</html>";

                // Affectation au corps du mail
                $mail->Body = $body_html;
                $mail->send();
                $_SESSION['message'] = "Un lien de réinitialisation a été envoyé à votre adresse email.";
            } catch (Exception $e) {
                $_SESSION['message'] = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
            }
        } else {
            $_SESSION['message'] = "Aucun compte associé à cette adresse email.";
        }

        header("Location: ../mot_de_passe_oublier.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur base de données : " . $e->getMessage();
        header("Location: ../mot_de_passe_oublier.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Formulaire invalide.";
    header("Location: ../mot_de_passe_oublier.php");
    exit();
}
?>