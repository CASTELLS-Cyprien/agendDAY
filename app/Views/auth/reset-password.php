<main class="form-container">
    <div class="form-box">
        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80">
            </a>
            <h1 class="form-title">Nouveau mot de passe</h1>
            <p class="form-subtitle">Choisissez un mot de passe d'au moins 8 caractères</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?php if ($formError): ?>
            <div class="alert alert--error" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <p><?= htmlspecialchars($formError) ?></p>
            </div>
        <?php endif; ?>

        <form action="/reinitialiser-mot-de-passe?token=<?= htmlspecialchars(urlencode($token)) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="newPassword">Nouveau mot de passe</label>
                <div class="password-field">
                    <input type="password" class="form-input" id="newPassword" name="newPassword" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="newPassword" aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirmNewPassword">Confirmer le mot de passe</label>
                <div class="password-field">
                    <input type="password" class="form-input" id="confirmNewPassword" name="confirmNewPassword" required
                        minlength="8" autocomplete="new-password" data-match-password="newPassword">
                    <button type="button" class="password-toggle" data-target="confirmNewPassword" aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Réinitialiser</button>
            </div>
            <div class="form-footer">
                <p><a href="/connexion" class="form-link">Retour à la connexion</a></p>
            </div>
        </form>
    </div>
</main>
