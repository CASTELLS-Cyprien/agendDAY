<?php
session_start();
$bdd = null;

require 'connexion_db.php';

if (isset($_GET['token'])) {
    try {
        $bdd = getDatabaseConnection();

        // Check if token is valid and not expired
        $sql = "SELECT * FROM users WHERE reset_token = :token AND reset_token_expiration > NOW()";
        $requete = $bdd->prepare($sql);
        $requete->bindParam(':token', $_GET['token']);
        $requete->execute();

        if ($requete->rowCount() == 0) {
            $_SESSION['message'] = "Lien de réinitialisation invalide ou expiré.";
            header("Location: ../mot_de_passe_oublier.php");
            exit();
        }

        $user = $requete->fetch(); // Récupère l'utilisateur associé au token

    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur : " . $e->getMessage();
        header("Location: ../mot_de_passe_oublier.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Aucun token fourni.";
    header("Location: ../mot_de_passe_oublier.php");
    exit();
}

if (isset($_POST['resetPassword'])) {
    if ($_POST['newPassword'] !== $_POST['confirmNewPassword']) {
        $_SESSION['message'] = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $newPassword = $_POST['newPassword'];

            // Vérifie si le nouveau mot de passe est identique à l'ancien
            if (password_verify($newPassword, $user['motDePasse'])) {
                $_SESSION['message'] = "Le nouveau mot de passe ne peut pas être identique à l'ancien.";
            } else {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                // Update password and clear reset token
                $sql = "UPDATE users SET motDePasse = :motDePasse, confirmerMotDePasse = :confirmerMotDePasse, reset_token = NULL, reset_token_expiration = NULL WHERE reset_token = :token";
                $requete = $bdd->prepare($sql);
                $requete->bindParam(':motDePasse', $hashedPassword);
                $requete->bindParam(':confirmerMotDePasse', $hashedPassword);
                $requete->bindParam(':token', $_GET['token']);
                $requete->execute();

                $_SESSION['message'] = "Mot de passe réinitialisé avec succès.";
                header("Location: ../connexion.php");
                exit();
            }

        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>AgendDAY - Réinitialiser le mot de passe</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    x
    <link rel="icon" href="../assets/images/logo.png">
</head>

<body>
    <div class="form-container">
        <div class="form-box">
            <?php
            // Display session message if it exists
            if (isset($_SESSION['message'])) {
                echo '<p class="form-error">' . htmlspecialchars($_SESSION['message']) . '</p>';
                unset($_SESSION['message']); // Clear the message after displaying
            }
            ?>
            <div class="form-header">
                <a href="../index.php"> <img src="../assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);"></a>
                <h1 class="form-title">Réinitialiser le mot de passe</h1>
                <p class="form-subtitle">Entrez votre nouveau mot de passe</p>
            </div>

            <form action="" method="post" onsubmit="return validateResetPasswordForm()">
                <div class="form-group">
                    <label class="form-label" for="newPassword">Nouveau mot de passe</label>
                    <input type="password" class="form-input" id="newPassword" name="newPassword" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmNewPassword">Confirmer le mot de passe</label>
                    <input type="password" class="form-input" id="confirmNewPassword" name="confirmNewPassword"
                        required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="form-submit" name="resetPassword">Réinitialiser</button>
                </div>

                <div class="form-footer">
                    <p>Vous avez un compte ? <a href="connexion.php" class="form-link">Se connecter</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function validateResetPasswordForm() {
            const newPassword = document.getElementById("newPassword").value;
            const confirmNewPassword = document.getElementById("confirmNewPassword").value;
            const message = document.getElementById("resetPasswordMessage");

            if (newPassword === "" || confirmNewPassword === "") {
                message.innerHTML = "Veuillez remplir tous les champs.";
                return false;
            }
            if (newPassword !== confirmNewPassword) {
                message.innerHTML = "Les mots de passe ne correspondent pas.";
                return false;
            }
            if (newPassword.length < 8) {
                message.innerHTML = "Le mot de passe doit contenir au moins 8 caractères.";
                return false;
            }
            message.innerHTML = "";
            return true;
        }
    </script>
</body>

</html>