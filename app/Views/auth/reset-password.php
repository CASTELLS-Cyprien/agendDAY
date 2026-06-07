<main class="form-container">
    <div class="form-box">
        <?php if ($flash): ?>
            <p class="form-error"><?= htmlspecialchars($flash) ?></p>
        <?php endif; ?>
        <?php if ($formError): ?>
            <p class="form-error"><?= htmlspecialchars($formError) ?></p>
        <?php endif; ?>

        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="form-logo">
            </a>
            <h1 class="form-title">Nouveau mot de passe</h1>
            <p class="form-subtitle">Choisissez un mot de passe sécurisé (8 caractères minimum)</p>
        </div>

        <form action="/reinitialiser-mot-de-passe?token=<?= htmlspecialchars($token) ?>" method="post">
            <div class="form-group">
                <label class="form-label" for="newPassword">Nouveau mot de passe</label>
                <input type="password" class="form-input" id="newPassword" name="newPassword" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label" for="confirmNewPassword">Confirmer le mot de passe</label>
                <input type="password" class="form-input" id="confirmNewPassword" name="confirmNewPassword" required
                    minlength="8">
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
