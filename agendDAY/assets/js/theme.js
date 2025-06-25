class ThemeManager {
    constructor() {
        this.initTheme();
        this.bindEvents();
    }

    initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        this.updateThemeIcons(savedTheme);
        this.updateHeroImage(savedTheme);
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        this.updateThemeIcons(newTheme);
        this.updateHeroImage(newTheme);
    }

    updateThemeIcons(theme) {
        document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar, #themeToggleHeader').forEach(toggle => {
            const icon = toggle.querySelector('i');
            if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
    }

    updateHeroImage(theme) {
        const heroImage = document.getElementById('heroImage');
        if (heroImage) {
            heroImage.src = theme === 'dark'
                ? 'assets/images/agendday-preview-dark.webp'
                : 'assets/images/agendday-preview-light.webp';
        }
    }

    bindEvents() {
        document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar, #themeToggleHeader').forEach(toggle => {
            toggle.addEventListener('click', () => this.toggleTheme());
        });
    }
}

document.addEventListener('DOMContentLoaded', () => new ThemeManager());
