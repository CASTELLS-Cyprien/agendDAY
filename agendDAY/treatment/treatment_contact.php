<?php
// Activer l'affichage des erreurs pour le débogage (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'email_connexion.php';

// Vérifier si le formulaire a été soumis correctement
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifier si tous les champs requis sont présents
    if (isset($_POST["nom"]) && isset($_POST["email"]) && isset($_POST["message"])) {
        // Nettoyer les données
        $nom = htmlspecialchars(trim($_POST["nom"]));
        $email = htmlspecialchars(trim($_POST["email"]));
        $message = htmlspecialchars(trim($_POST["message"]));

        // Valider l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Erreur : Adresse email invalide.";
            exit();
        }

        try {
            // Configurer l'email avec PHPMailer
            $mail = getEmailConnection();
            $mail->addAddress(EMAIL_FROM);
            $mail->Subject = "Nouveau message de contact de $nom";
            $mail->Body = "<p><strong>Nom :</strong> $nom</p>
                           <p><strong>Email :</strong> $email</p>
                           <p><strong>Message :</strong><br>$message</p>";
            $mail->AltBody = "Nom : $nom\nEmail : $email\nMessage :\n$message";

            // Envoyer l'email
            if ($mail->send()) {
                // Rediriger vers la page de confirmation
                header("Location: contact_confirmation.html");
                exit();
            } else {
                echo "Erreur : Échec de l'envoi de l'email. Veuillez réessayer plus tard.";
            }
        } catch (Exception $e) {
            echo "Erreur : Échec de l'envoi de l'email. " . $e->getMessage();
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
        echo "Erreur : Les champs suivants sont manquants : " . implode(", ", $missing);
    }
} else {
    echo "Erreur : La requête n'est pas de type POST.";
}
?>