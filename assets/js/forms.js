// ===== COMPORTEMENTS COMMUNS AUX FORMULAIRES (connexion, inscription, contact, etc.) =====

document.addEventListener('DOMContentLoaded', () => {
    // === reCAPTCHA OBLIGATOIRE (formulaire de contact) ===
    // Complément de la vérification serveur : évite un aller-retour inutile.
    // Enregistré avant l'état de chargement pour pouvoir annuler l'envoi.
    document.querySelectorAll('form[data-require-recaptcha]').forEach(form => {
        form.addEventListener('submit', event => {
            const response = form.querySelector('[name="g-recaptcha-response"]');
            const error = document.getElementById('recaptchaError');
            const missing = !response || !response.value;
            if (error) {
                error.hidden = !missing;
            }
            if (missing) {
                event.preventDefault();
            }
        });
    });

    // === ÉTAT DE CHARGEMENT DU BOUTON SUBMIT ===
    // Désactive le bouton à la soumission pour éviter les double-clics pendant
    // les traitements qui peuvent prendre plusieurs secondes (envoi SMTP, reCAPTCHA).
    document.querySelectorAll('.form-submit').forEach(btn => {
        btn.dataset.originalText = btn.textContent.trim();
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented || !form.checkValidity()) return;

            const btn = form.querySelector('.form-submit');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.innerHTML = '<span class="spinner" aria-hidden="true"></span> Veuillez patienter…';
            }
        });
    });

    // Retour arrière depuis le cache du navigateur : le bouton redevient utilisable
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        document.querySelectorAll('.form-submit[aria-busy="true"]').forEach(btn => {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btn.textContent = btn.dataset.originalText || 'Envoyer';
        });
    });

    // === VÉRIFICATION "LES MOTS DE PASSE CORRESPONDENT" EN DIRECT ===
    // data-match-password="idDuChampOriginal" sur le champ de confirmation
    document.querySelectorAll('[data-match-password]').forEach(confirmField => {
        const original = document.getElementById(confirmField.dataset.matchPassword);
        if (!original) return;

        const check = () => {
            confirmField.setCustomValidity(
                confirmField.value !== original.value ? 'Les mots de passe ne correspondent pas.' : ''
            );
        };
        original.addEventListener('input', check);
        confirmField.addEventListener('input', check);
    });

    // === BASCULE AFFICHER / MASQUER LE MOT DE PASSE ===
    // data-target="idDuChampPassword" sur le bouton .password-toggle
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        const input = document.getElementById(toggle.dataset.target);
        if (!input) return;

        toggle.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.querySelector('i').className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
            toggle.setAttribute('aria-label', showing ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
        });
    });
});
