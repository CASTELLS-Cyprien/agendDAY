<main class="form-container">
    <div class="form-box">
        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80">
            </a>
            <h1 class="form-title">Connexion</h1>
            <p class="form-subtitle">Connectez-vous à votre compte AgendDAY</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <form action="/connexion" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" class="form-input" id="email" name="email" required autocomplete="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="motDePasse">Mot de passe</label>
                <div class="password-field">
                    <input type="password" class="form-input" id="motDePasse" name="motDePasse" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="motDePasse" aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Se connecter</button>
            </div>
            <div class="form-footer">
                <p>Vous n'avez pas de compte ? <a href="/inscription" class="form-link">S'inscrire</a></p>
                <p>Mot de passe oublié ? <a href="/mot-de-passe-oublie" class="form-link">Réinitialiser</a></p>
            </div>
        </form>
    </div>
</main>
