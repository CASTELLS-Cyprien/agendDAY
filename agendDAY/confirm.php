<?php
session_start();

try {
    $bdd = new PDO("mysql:host=castelpcyp.mysql.db;dbname=castelpcyp", "castelpcyp", "reRPhrkHNNxoz69TfNKF");
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['token'])) {
        // Check if token is valid
        $sql = "SELECT * FROM users WHERE confirmation_token = :token AND is_confirmed = 0";
        $requete = $bdd->prepare($sql);
        $requete->bindParam(':token', $_GET['token']);
        $requete->execute();

        if ($requete->rowCount() > 0) {
            // Confirm the account
            $sql = "UPDATE users SET is_confirmed = 1, confirmation_token = NULL WHERE confirmation_token = :token";
            $requete = $bdd->prepare($sql);
            $requete->bindParam(':token', $_GET['token']);
            $requete->execute();

            $_SESSION['message'] = "Votre compte a été confirmé avec succès ! Vous pouvez maintenant vous connecter.";
            header("Location: connexion.php");
            exit();
        } else {
            $_SESSION['message'] = "Lien de confirmation invalide ou compte déjà confirmé.";
            header("Location: connexion.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Aucun token fourni.";
        header("Location: connexion.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur : " . $e->getMessage();
    header("Location: connexion.php");
    exit();
}
?>