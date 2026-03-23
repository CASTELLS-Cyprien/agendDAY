<?php
session_start();

require_once __DIR__ . '/treatment/connexion_db.php';

try {
    $bdd = getDatabaseConnection();

    if (!isset($_GET['token']) || empty($_GET['token'])) {
        $_SESSION['message'] = "Aucun token fourni.";
        header("Location: connexion.php");
        exit();
    }

    $token = $_GET['token'];

    // Vérifier si le token est valide et le compte non encore confirmé
    $stmt = $bdd->prepare(
        "SELECT id FROM users WHERE confirmation_token = :token AND is_confirmed = 0"
    );
    $stmt->execute([':token' => $token]);

    if ($stmt->rowCount() > 0) {
        $upd = $bdd->prepare(
            "UPDATE users SET is_confirmed = 1, confirmation_token = NULL
             WHERE confirmation_token = :token"
        );
        $upd->execute([':token' => $token]);

        $_SESSION['message'] = "Votre compte a été confirmé ! Vous pouvez maintenant vous connecter.";
    } else {
        $_SESSION['message'] = "Lien de confirmation invalide ou compte déjà confirmé.";
    }

} catch (PDOException $e) {
    error_log("Erreur confirm.php : " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue. Veuillez réessayer.";
}

header("Location: connexion.php");
exit();