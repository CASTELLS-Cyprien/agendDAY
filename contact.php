<?php
session_start();
include 'head.php';
$title = "Contact";
?>

<body>
    <div class="form-container">
        <div class="form-box">
            <?php
            if (isset($_SESSION['message'])) {
                echo '<p class="form-error">' . htmlspecialchars($_SESSION['message']) . '</p>';
                unset($_SESSION['message']);
            }
            ?>
            <div class="form-header">
                <a href="/">
                    <img src="/assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width: 80px; margin-bottom: var(--spacing-4);">
                </a>
                <h1 class="form-title">Contactez-nous</h1>
                <p class="form-subtitle">Nous sommes là pour vous aider</p>
            </div>

            <form action="/treatment/treatment_contact.php" method="post" id="contact-form">
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
                <!-- Widget reCAPTCHA -->
                <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="6LdxhW8rAAAAAC4x5quwv6aZW1RHxyWkmk34Br_p"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="form-submit">Envoyer</button>
                </div>
            </form>

            <div class="form-footer">
                <h3 style="margin-bottom: var(--spacing-4);">Retrouvez-nous rapidement</h3>
                <p><i class="fas fa-envelope"></i> <a href="mailto:agendday@castells-cyprien.ovh"
                        class="form-link">agendday@castells-cyprien.ovh</a></p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/theme.js"></script>
    <script>
        document.getElementById('contact-form').addEventListener('submit', function (event) {
            const recaptcha = document.querySelector('.g-recaptcha-response').value;
            if (!recaptcha) {
                event.preventDefault();
                alert('Veuillez cocher le reCAPTCHA.');
            }
        });
    </script>
</body>

</html>