<main class="form-container">
    <div class="form-box">
        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80">
            </a>
            <h1 class="form-title">Inscription</h1>
            <p class="form-subtitle">Créez votre compte AgendDAY</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <form action="/inscription" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="nomUtilisateur">Nom d'utilisateur</label>
                <input type="text" class="form-input" id="nomUtilisateur" name="nomUtilisateur" required maxlength="100"
                    autocomplete="nickname" value="<?= htmlspecialchars($old['nomUtilisateur'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" class="form-input" id="email" name="email" required maxlength="255" autocomplete="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="motDePasse">Mot de passe <span class="form-hint">(8 caractères minimum)</span></label>
                <div class="password-field">
                    <input type="password" class="form-input" id="motDePasse" name="motDePasse" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="motDePasse" aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirmerMotDePasse">Confirmer le mot de passe</label>
                <div class="password-field">
                    <input type="password" class="form-input" id="confirmerMotDePasse" name="confirmerMotDePasse"
                        required minlength="8" autocomplete="new-password" data-match-password="motDePasse">
                    <button type="button" class="password-toggle" data-target="confirmerMotDePasse" aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">S'inscrire</button>
            </div>
            <div class="form-footer">
                <p>Vous avez déjà un compte ? <a href="/connexion" class="form-link">Se connecter</a></p>
                <p>Vos données sont traitées selon notre <a href="/politique-confidentialite" class="form-link">politique de confidentialité</a>.</p>
            </div>
        </form>
    </div>
</main>
