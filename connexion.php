<?php
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['id'])) {
    header("Location: /calendrier");
    exit();
}
include 'head.php';
$title = "Connexion";
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
                <a href="/"> <img src="/assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);"></a>
                <h1 class="form-title">Connexion</h1>
                <p class="form-subtitle">Connectez-vous à votre compte AgendDAY</p>
            </div>

            <form action="/treatment/treatment_connexion.php" method="post">
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
                    <p>Vous n'avez pas de compte ? <a href="/inscription" class="form-link">S'inscrire</a></p>
                    <p>Vous avez oubliez votre mot de passe ? <a href="/mot-de-passe-oublie" class="form-link">Mot de
                            passe oublier</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/theme.js"></script>
</body>

</html>