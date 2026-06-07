// ===== COMPORTEMENTS COMMUNS AUX FORMULAIRES (connexion, inscription, contact, etc.) =====

document.addEventListener('DOMContentLoaded', () => {
    // === ÉTAT DE CHARGEMENT DU BOUTON SUBMIT ===
    // Désactive le bouton à la soumission pour éviter les double-clics pendant
    // les traitements qui peuvent prendre plusieurs secondes (envoi SMTP, reCAPTCHA).
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) return;

            const btn = form.querySelector('.form-submit');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Veuillez patienter…';
            }
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
