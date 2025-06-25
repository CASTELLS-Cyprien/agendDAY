<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>AgendDAY - Inscription</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="icon" href="assets/images/logo.png">
</head>

<body>
    <div class="form-container">
        <div class="form-box">
            <?php
            if (isset($_SESSION['message'])) {
                echo '<p class="form-error">' . htmlspecialchars($_SESSION['message']) . '</p>';
                unset($_SESSION['message']);
            }
            ?>
            <div class="form-header">
                <a href="index.php"> <img src="assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);"></a>
                <h1 class="form-title">Inscription</h1>
                <p class="form-subtitle">Créez votre compte AgendDAY</p>
            </div>

            <form action="treatment/treatment_inscription.php" method="post">
                <input type="hidden" name="Inscription" value="1"> <!-- Champ caché pour satisfaire la condition -->
                <div class="form-group">
                    <label class="form-label" for="nomUtilisateur">Nom d'utilisateur</label>
                    <input type="text" class="form-input" id="nomUtilisateur" name="nomUtilisateur" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-input" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="motDePasse">Mot de passe</label>
                    <input type="password" class="form-input" id="motDePasse" name="motDePasse" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmerMotDePasse">Confirmer le mot de passe</label>
                    <input type="password" class="form-input" id="confirmerMotDePasse" name="confirmerMotDePasse"
                        required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="form-submit">S'inscrire</button>
                </div>
                <div class="form-footer">
                    <p>Vous avez déjà un compte ? <a href="connexion.php" class="form-link">Se connecter</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="assets/js/theme.js"></script>
</body>

</html>