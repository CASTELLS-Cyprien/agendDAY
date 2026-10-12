// ===== BASCULE DU THÈME CLAIR / SOMBRE =====
// Tout bouton portant [data-theme-toggle] bascule le thème ; le choix est mémorisé.
// Sans choix mémorisé, le thème suit la préférence du système (cf. theme-init.js).
(function () {
    'use strict';

    var TOGGLES = '[data-theme-toggle]';

    function current() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);

        var nextLabel = theme === 'dark' ? 'Thème clair' : 'Thème sombre';
        document.querySelectorAll(TOGGLES).forEach(function (toggle) {
            var icon = toggle.querySelector('i');
            var label = toggle.querySelector('[data-theme-label]');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            if (label) {
                label.textContent = nextLabel;
            } else {
                toggle.setAttribute('aria-label', 'Activer le ' + nextLabel.toLowerCase());
            }
        });

        var hero = document.getElementById('heroImage');
        if (hero) {
            hero.src = '/assets/images/agendday-preview-' + theme + '.webp';
            hero.alt = "Aperçu du calendrier AgendDAY en thème " + (theme === 'dark' ? 'sombre' : 'clair');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        apply(current());
        document.querySelectorAll(TOGGLES).forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var next = current() === 'dark' ? 'light' : 'dark';
                try {
                    localStorage.setItem('theme', next);
                } catch (e) {
                    // Choix non mémorisé, mais appliqué pour la page courante
                }
                apply(next);
            });
        });
    });
})();
