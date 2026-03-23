<?php
session_start();

require_once __DIR__ . '/email_connexion.php';
require_once __DIR__ . '/connexion_db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['message'] = "Erreur : Formulaire non soumis correctement.";
    header("Location: /inscription");
    exit();
}

try {
    $bdd = getDatabaseConnection();

    if (empty($_POST['nomUtilisateur']) || empty($_POST['email']) || empty($_POST['motDePasse']) || empty($_POST['confirmerMotDePasse'])) {
        $_SESSION['message'] = "Veuillez remplir tous les champs obligatoires.";
        header("Location: /inscription");
        exit();
    }

    $nomUtilisateur = htmlspecialchars(trim($_POST['nomUtilisateur']));
    $email = htmlspecialchars(trim($_POST['email']));
    $motDePasse = $_POST['motDePasse'];
    $confirmerMotDePasse = $_POST['confirmerMotDePasse'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Adresse email invalide.";
        header("Location: /inscription");
        exit();
    }

    // Vérifier si l'email existe déjà
    $stmt = $bdd->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Cet email est déjà enregistré.";
        header("Location: /connexion");
        exit();
    }

    if ($motDePasse !== $confirmerMotDePasse) {
        $_SESSION['message'] = "Les mots de passe ne correspondent pas.";
        header("Location: /inscription");
        exit();
    }

    $hashedPassword = password_hash($motDePasse, PASSWORD_BCRYPT);
    $confirmationToken = bin2hex(random_bytes(50));

    $stmt = $bdd->prepare(
        "INSERT INTO users (nomUtilisateur, email, motDePasse, confirmation_token, is_confirmed)
         VALUES (:nomUtilisateur, :email, :motDePasse, :confirmationToken, 0)"
    );
    $stmt->execute([
        ':nomUtilisateur' => $nomUtilisateur,
        ':email' => $email,
        ':motDePasse' => $hashedPassword,
        ':confirmationToken' => $confirmationToken,
    ]);

    // Envoi de l'email de confirmation
    $confirmationLink = "https://agendday.castells-cyprien.ovh/confirm.php?token=" . $confirmationToken;

    $mail = getEmailConnection();
    $mail->addAddress($email);
    $mail->Subject = '=?UTF-8?B?' . base64_encode("Confirmez votre inscription - agendDAY") . '?=';

    $mail->Body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
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
        <div class='header'><h1>Confirmation d'inscription</h1></div>
        <div class='content'>
            <p>Bonjour " . htmlspecialchars($nomUtilisateur) . ",</p>
            <p>Merci de vous être inscrit sur AgendDAY. Cliquez ci-dessous pour confirmer votre adresse email :</p>
            <p><a href='" . $confirmationLink . "' class='cta-button'>Confirmer mon compte</a></p>
            <p>Ou copiez ce lien dans votre navigateur :<br>
               <a href='" . $confirmationLink . "' style='color:#485fc7;'>" . $confirmationLink . "</a></p>
            <p><small>Ce lien expire dans 24 heures.</small></p>
        </div>
        <div class='footer'>Cordialement,<br>L'équipe AgendDAY</div>
    </div>
</body>
</html>";

    $mail->AltBody = "Bonjour {$nomUtilisateur},\n\n"
        . "Confirmez votre compte AgendDAY en cliquant sur ce lien :\n{$confirmationLink}\n\n"
        . "Ce lien expire dans 24 heures.\n\nL'équipe AgendDAY";

    $mail->send();
    $_SESSION['message'] = "Inscription réussie ! Un email de confirmation a été envoyé.";

} catch (Exception $e) {
    error_log("Erreur email inscription : " . $e->getMessage());
    $_SESSION['message'] = "Inscription réussie, mais erreur lors de l'envoi de l'email de confirmation.";
} catch (PDOException $e) {
    error_log("Erreur DB inscription : " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
}

header("Location: /connexion");
exit();