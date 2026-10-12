<main class="form-container">
    <div class="form-box">
        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80">
            </a>
            <h1 class="form-title">Contactez-nous</h1>
            <p class="form-subtitle">Une question, une suggestion ? Écrivez-nous.</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <form action="/contact" method="post" id="contactForm" data-require-recaptcha>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="nom">Nom</label>
                <input type="text" class="form-input" id="nom" name="nom" required maxlength="100" autocomplete="name"
                    value="<?= htmlspecialchars($old['nom'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" class="form-input" id="email" name="email" required autocomplete="email"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="message">Message</label>
                <textarea class="form-input form-textarea" id="message" name="message" required maxlength="5000"><?= htmlspecialchars($old['message'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"></div>
                <p class="form-error" id="recaptchaError" role="alert" hidden>Veuillez cocher la case « Je ne suis pas un robot ».</p>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Envoyer</button>
            </div>
        </form>

        <div class="form-footer">
            <h2 class="form-footer__heading">Retrouvez-nous rapidement</h2>
            <p>
                <i class="fas fa-envelope" aria-hidden="true"></i>
                <a href="mailto:agendday@castells-cyprien.ovh" class="form-link">agendday@castells-cyprien.ovh</a>
            </p>
        </div>
    </div>
</main>
