<main class="form-container">
    <div class="form-box">
        <?php if ($flash): ?>
            <p class="form-error"><?= htmlspecialchars($flash) ?></p>
        <?php endif; ?>

        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="form-logo">
            </a>
            <h1 class="form-title">Mot de passe oublié</h1>
            <p class="form-subtitle">Réinitialisez votre mot de passe AgendDAY</p>
        </div>

        <form action="/mot-de-passe-oublie" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-input" id="email" name="email" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Réinitialiser le mot de passe</button>
            </div>
            <div class="form-footer">
                <p>Vous n'avez pas de compte ? <a href="/inscription" class="form-link">S'inscrire</a></p>
                <p>Vous avez déjà un compte ? <a href="/connexion" class="form-link">Se connecter</a></p>
            </div>
        </form>
    </div>
</main>
