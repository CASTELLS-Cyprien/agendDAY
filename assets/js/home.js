// home.js
const menuToggle = document.querySelector('.menu-toggle');
const headerNav = document.querySelector('.header-nav');

if (menuToggle && headerNav) {
    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', !isExpanded);
        menuToggle.classList.toggle('active');
        headerNav.classList.toggle('active');

        // Ajouter un effet de fondu
        if (!isExpanded) {
            headerNav.style.opacity = '0';
            setTimeout(() => {
                headerNav.style.opacity = '1';
            }, 50);
        }
    });

    // Fermer le menu en cliquant sur un lien
    headerNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.classList.remove('active');
            headerNav.classList.remove('active');
        });
    });

    // Fermer le menu en appuyant sur Échap
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && headerNav.classList.contains('active')) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.classList.remove('active');
            headerNav.classList.remove('active');
        }
    });
}

// Animation au scroll (inchangé)
document.addEventListener('DOMContentLoaded', () => {
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-slide-up').forEach(element => observer.observe(element));
    } else {
        document.querySelectorAll('.animate-slide-up').forEach(element => element.classList.add('is-visible'));
    }
});

// Effet de défilement en-tête (inchangé)
const header = document.querySelector('.header');
let lastScrollTop = 0;

if (header) {
    window.addEventListener('scroll', () => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    });
}