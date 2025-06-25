<?php
session_start();

require_once 'email_connexion.php';
require_once 'connexion_db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bdd = null;
    try {
        $bdd = getDatabaseConnection();

        if (empty($_POST['nomUtilisateur']) || empty($_POST['email']) || empty($_POST['motDePasse']) || empty($_POST['confirmerMotDePasse'])) {
            $_SESSION['message'] = "Veuillez remplir tous les champs obligatoires.";
            header("Location: ../inscription.php");
            exit();
        }
        // Sanitize inputs
        $nomUtilisateur = htmlspecialchars(trim($_POST['nomUtilisateur']));
        $email = htmlspecialchars(trim($_POST['email']));
        $motDePasse = $_POST['motDePasse'];
        $confirmerMotDePasse = $_POST['confirmerMotDePasse'];

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = "Adresse email invalide.";
            header("Location: ../inscription.php");
            exit();
        }

        // Check if email already exists
        $sql = "SELECT * FROM users WHERE email = :email";
        $requete = $bdd->prepare($sql);
        $requete->bindParam(':email', $email);
        $requete->execute();

        if ($requete->rowCount() > 0) {
            $_SESSION['message'] = "Cet email est déjà enregistré.";
            header("Location: ../connexion.php");
            exit();
        }

        // Validate password confirmation
        if ($motDePasse !== $confirmerMotDePasse) {
            $_SESSION['message'] = "Les mots de passe ne correspondent pas.";
            header("Location: ../inscription.php");
            exit();
        }

        // Hash the password
        $hashedPassword = password_hash($motDePasse, PASSWORD_BCRYPT);

        // Generate a confirmation token
        $confirmationToken = bin2hex(random_bytes(50));

        // Insert user into database
        $sql = "INSERT INTO users (nomUtilisateur, email, motDePasse, confirmation_token, is_confirmed) 
                VALUES (:nomUtilisateur, :email, :motDePasse, :confirmationToken, 0)";
        $requete = $bdd->prepare($sql);
        $requete->bindParam(':nomUtilisateur', $nomUtilisateur);
        $requete->bindParam(':email', $email);
        $requete->bindParam(':motDePasse', $hashedPassword);
        $requete->bindParam(':confirmationToken', $confirmationToken);
        $requete->execute();

        // Send confirmation email
        try {
            $mail = getEmailConnection();
            $mail->addAddress($email);
            $mail->Subject = "Confirmez votre inscription - agendDAY";

            $confirmationLink = "http://castells-cyprien.ovh/agendDAY/confirm.php?token=" . $confirmationToken;
            error_log("Confirmation link generated: " . $confirmationLink);

            $mail->Body = "<!DOCTYPE html>
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
        .cta-button { display: inline-block; padding: 12px 20px; background-color: #485fc7; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 15px 0; border: 1px solid #3a7bc8; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #666666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Confirmation d'inscription</h1>
        </div>
        <div class='content'>
            <p>Bonjour " . htmlspecialchars($nomUtilisateur) . ",</p>
            <p>Merci de vous être inscrit sur agendDAY. Pour finaliser votre inscription, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous :</p>
            <p><a href='$confirmationLink' class='cta-button'>Confirmer mon compte</a></p>
            <p>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
            <p><a href='$confirmationLink' style='color: #485fc7; text-decoration: none;'>$confirmationLink</a></p>
            <p>Ce lien expire dans 24 heures.</p>
        </div>
        <div class='footer'>
            <p>Cordialement,<br>L'équipe agendDAY</p>
        </div>
    </div>
</body>
</html>";

            $mail->AltBody = "Bonjour " . htmlspecialchars($nomUtilisateur) . ",\n\n"
                . "Merci de vous être inscrit sur agendDAY. Pour finaliser votre inscription, veuillez confirmer votre adresse email en cliquant sur le lien suivant :\n"
                . "$confirmationLink\n\n"
                . "Ce lien expire dans 24 heures.\n\n"
                . "Cordialement,\nL'équipe agendDAY";

            $mail->send();
            $_SESSION['message'] = "Inscription réussie ! Un email de confirmation a été envoyé à votre adresse email.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Inscription réussie, mais erreur lors de l'envoi de l'email de confirmation : " . $e->getMessage();
            error_log("Email sending failed: " . $e->getMessage());
        }

        // Close the connection
        $bdd = null;

        // Redirect to connexion.php
        header("Location: ../connexion.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['message'] = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
        error_log("Database error: " . $e->getMessage() . " in " . $e->getFile() . " at line " . $e->getLine());
        header("Location: ../inscription.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Erreur : Formulaire non soumis correctement.";
    header("Location: ../inscription.php");
    exit();
}
?>