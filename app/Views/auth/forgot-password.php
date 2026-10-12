<main class="form-container">
    <div class="form-box">
        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80">
            </a>
            <h1 class="form-title">Mot de passe oublié</h1>
            <p class="form-subtitle">Recevez par e-mail un lien pour choisir un nouveau mot de passe</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <form action="/mot-de-passe-oublie" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" class="form-input" id="email" name="email" required autocomplete="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Envoyer le lien</button>
            </div>
            <div class="form-footer">
                <p>Vous n'avez pas de compte ? <a href="/inscription" class="form-link">S'inscrire</a></p>
                <p>Vous avez déjà un compte ? <a href="/connexion" class="form-link">Se connecter</a></p>
            </div>
        </form>
    </div>
</main>
