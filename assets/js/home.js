document.addEventListener('DOMContentLoaded', () => {
    // === MENU HAMBURGER ===
    const menuToggle = document.querySelector('.menu-toggle');
    const headerNav  = document.querySelector('.header-nav');

    if (menuToggle && headerNav) {
        menuToggle.addEventListener('click', () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
            menuToggle.classList.toggle('active');
            headerNav.classList.toggle('active');

            if (!isExpanded) {
                headerNav.style.opacity = '0';
                setTimeout(() => { headerNav.style.opacity = '1'; }, 50);
            }
        });

        headerNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.classList.remove('active');
                headerNav.classList.remove('active');
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && headerNav.classList.contains('active')) {
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.classList.remove('active');
                headerNav.classList.remove('active');
            }
        });
    }

    // === ANIMATIONS AU SCROLL ===
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(({ target, isIntersecting }) => {
                    if (isIntersecting) target.classList.add('is-visible');
                });
            },
            { threshold: 0.1 }
        );
        document.querySelectorAll('.animate-slide-up').forEach(el => observer.observe(el));
    } else {
        document.querySelectorAll('.animate-slide-up').forEach(el => el.classList.add('is-visible'));
    }

    // === EFFET SCROLL HEADER ===
    const header = document.querySelector('.header');
    if (header) {
        let lastScrollTop = 0;
        window.addEventListener('scroll', () => {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            header.classList.toggle('scrolled', scrollTop > lastScrollTop && scrollTop > 100);
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }, { passive: true });
    }
});
