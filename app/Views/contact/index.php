<main class="form-container">
    <div class="form-box">
        <?php if ($flash): ?>
            <p class="form-error"><?= htmlspecialchars($flash) ?></p>
        <?php endif; ?>

        <div class="form-header">
            <a href="/">
                <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="form-logo">
            </a>
            <h1 class="form-title">Contactez-nous</h1>
            <p class="form-subtitle">Nous sommes là pour vous aider</p>
        </div>

        <form action="/contact" method="post" id="contactForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <div class="form-group">
                <label class="form-label" for="nom">Nom</label>
                <input type="text" class="form-input" id="nom" name="nom" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-input" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="message">Message</label>
                <textarea class="form-input form-textarea" id="message" name="message" required></textarea>
            </div>
            <div class="form-group">
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="form-submit">Envoyer</button>
            </div>
        </form>

        <div class="form-footer">
            <h3 class="form-footer__heading">Retrouvez-nous rapidement</h3>
            <p>
                <i class="fas fa-envelope" aria-hidden="true"></i>
                <a href="mailto:agendday@castells-cyprien.ovh" class="form-link">agendday@castells-cyprien.ovh</a>
            </p>
        </div>
    </div>
</main>

<script>
/* Validation client reCAPTCHA — complément de la validation serveur dans ContactController */
document.getElementById('contactForm').addEventListener('submit', (event) => {
    const recaptchaResponse = document.querySelector('[name="g-recaptcha-response"]');
    if (!recaptchaResponse || !recaptchaResponse.value) {
        event.preventDefault();
        alert('Veuillez compléter le reCAPTCHA.');
    }
});
</script>
