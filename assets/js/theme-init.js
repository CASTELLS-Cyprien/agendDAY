// Chargé de façon bloquante dans <head> : applique le thème avant le premier rendu
// (pas de flash du thème clair). Fichier externe plutôt que script inline pour
// permettre une Content-Security-Policy sans 'unsafe-inline'.
(function () {
    var theme = 'light';
    try {
        var saved = localStorage.getItem('theme');
        if (saved === 'dark' || saved === 'light') {
            theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        }
    } catch (e) {
        // Stockage indisponible (navigation privée stricte) : thème clair
    }
    document.documentElement.setAttribute('data-theme', theme);
    // Indique que le JavaScript est actif (animations progressives de la page d'accueil)
    document.documentElement.classList.add('js');
})();
