<?php
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['id'])) {
    header("Location: calendar.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>AgendDAY - Connexion</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/forms.css">

    <link rel="icon" href="assets/images/logo.png">
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
                <a href="index.php"> <img src="assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);"></a>
                <h1 class="form-title">Connexion</h1>
                <p class="form-subtitle">Connectez-vous à votre compte AgendDAY</p>
            </div>

            <form action="treatment/treatment_connexion.php" method="post">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-input" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="motDePasse">Mot de passe</label>
                    <input type="password" class="form-input" id="motDePasse" name="motDePasse" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="form-submit" name="Connexion">Se connecter</button>
                </div>

                <div class="form-footer">
                    <p>Vous n'avez pas de compte ? <a href="inscription.php" class="form-link">S'inscrire</a></p>
                    <p>Vous avez oubliez votre mot de passe ? <a href="mot_de_passe_oublier.php" class="form-link">Mot de passe oublier</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/theme.js"></script>
</body>

</html>