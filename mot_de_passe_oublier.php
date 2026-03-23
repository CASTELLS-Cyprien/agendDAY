<?php session_start();
include 'head.php';
$title = "Mot de Passe Oublié";
?>

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
                <a href="/"> <img src="assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);"></a>
                <h1 class="form-title">Mot de passe oublier</h1>
                <p class="form-subtitle">Réinitialiser votre mot de passe AgendDAY</p>
            </div>

            <form action="/treatment/treatment_mot_de_passe_oublier.php" method="post">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-input" id="email" name="email" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="form-submit" name="renitialiserMotDePasse">Réinitialiser mot de
                        passe</button>
                </div>
                <div class="form-footer">
                    <p>Vous n'avez pas de compte ? <a href="/inscription" class="form-link">S'inscrire</a></p>
                    <p>Vous avez déja un compte ? <a href="/connexion" class="form-link">Se connecter</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/theme.js"></script>
</body>