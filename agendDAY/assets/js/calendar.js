// ===== JAVASCRIPT POUR LE CALENDRIER =====

class CalendarApp {
    constructor() {
        this.currentDate = new Date();
        this.selectedDate = new Date();
        this.events = [];
        this.months = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        this.dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

        // Variables supplémentaires de script.js
        this.activeDay = this.currentDate.getDate();
        this.month = this.currentDate.getMonth();
        this.year = this.currentDate.getFullYear();

        this.init();
    }

    init() {
        this.initTheme();
        this.bindEvents();
        this.loadEvents();
        this.renderCalendar();
        this.updateSelectedDate();
        this.setInitialDate();
    }

    initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        this.updateThemeIcons(savedTheme);
    }

    updateThemeIcons(theme) {
        const themeToggles = document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar');
        themeToggles.forEach(toggle => {
            const icon = toggle.querySelector('i');
            const text = toggle.querySelector('span');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            if (text) {
                text.textContent = theme === 'dark' ? 'Thème clair' : 'Thème sombre';
            }
        });
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        this.updateThemeIcons(newTheme);
    }

    bindEvents() {
        // Navigation du calendrier
        document.getElementById('prevMonth').addEventListener('click', () => this.previousMonth());
        document.getElementById('nextMonth').addEventListener('click', () => this.nextMonth());
        document.getElementById('todayBtn').addEventListener('click', () => this.goToToday());
        document.getElementById('gotoBtn').addEventListener('click', () => this.goToDate());

        // Modal d'événement
        document.getElementById('addEventBtn').addEventListener('click', () => this.openEventModal());
        document.getElementById('modalClose').addEventListener('click', () => this.closeEventModal());
        document.getElementById('cancelBtn').addEventListener('click', () => this.closeEventModal());

        // Formulaire d'événement
        document.getElementById('eventForm').addEventListener('submit', (e) => this.handleEventSubmit(e));

        // Menu sidebar
        document.getElementById('menuToggle').addEventListener('click', () => this.toggleSidebar());
        document.getElementById('sidebarOverlay').addEventListener('click', () => this.closeSidebar());

        // Changement de thème
        const themeToggles = document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar');
        themeToggles.forEach(toggle => {
            toggle.addEventListener('click', () => this.toggleTheme());
        });

        // Input de navigation rapide
        const dateInput = document.getElementById('dateInput');
        dateInput.addEventListener('input', (e) => this.formatDateInput(e));
        dateInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.goToDate();
        });

        // Fermer la modal en cliquant à l'extérieur
        document.getElementById('eventModal').addEventListener('click', (e) => {
            if (e.target.id === 'eventModal') this.closeEventModal();
        });

        // Échapper pour fermer la modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeEventModal();
                this.closeSidebar();
            }
        });

        // Gestion des heures d'événements (de script.js)
        const eventTimeFrom = document.querySelector('.event-time-from');
        if (eventTimeFrom) {
            eventTimeFrom.addEventListener('input', (e) => {
                eventTimeFrom.value = eventTimeFrom.value.replace(/[^0-9:]/g, '');
                if (eventTimeFrom.value.length === 2) eventTimeFrom.value += ':';
                if (eventTimeFrom.value.length > 5) eventTimeFrom.value = eventTimeFrom.value.slice(0, 5);
            });
        }

        // Limitation des caractères pour le titre (de script.js)
        const eventTitle = document.querySelector('.event-name');
        if (eventTitle) {
            eventTitle.addEventListener('input', (e) => {
                eventTitle.value = eventTitle.value.slice(0, 60);
            });
        }
    }

    setInitialDate() {
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');

        if (dateParam) {
            const date = new Date(dateParam);
            if (!isNaN(date.getTime())) {
                this.selectedDate = date;
                this.currentDate = new Date(date.getFullYear(), date.getMonth(), 1);
                this.activeDay = date.getDate();
                this.month = date.getMonth();
                this.year = date.getFullYear();
                this.renderCalendar();
                this.updateSelectedDate();
                this.loadEventsForDate(this.formatDate(this.selectedDate));
            }
        } else {
            this.selectedDate = new Date();
            this.activeDay = this.selectedDate.getDate();
            this.month = this.selectedDate.getMonth();
            this.year = this.selectedDate.getFullYear();
            this.loadEventsForDate(this.formatDate(this.selectedDate));
        }

        document.getElementById('selectedDateInput').value = this.formatDate(this.selectedDate);
    }

    renderCalendar() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();

        document.getElementById('calendarTitle').textContent = `${this.months[month]} ${year}`;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        const prevMonth = new Date(year, month - 1, 0);
        const daysInPrevMonth = prevMonth.getDate();

        const calendarDays = document.getElementById('calendarDays');
        calendarDays.innerHTML = '';

        // Jours du mois précédent
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            const day = daysInPrevMonth - i;
            const dayElement = this.createDayElement(day, 'prev-month', year, month - 1);
            calendarDays.appendChild(dayElement);
        }

        // Jours du mois actuel
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = this.createDayElement(day, 'current-month', year, month);
            calendarDays.appendChild(dayElement);
        }

        // Jours du mois suivant
        const totalCells = calendarDays.children.length;
        const remainingCells = 42 - totalCells;

        for (let day = 1; day <= remainingCells; day++) {
            const dayElement = this.createDayElement(day, 'next-month', year, month + 1);
            calendarDays.appendChild(dayElement);
        }
    }

    createDayElement(day, monthType, year, month) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;

        const dayDate = new Date(year, month, day);
        const today = new Date();

        if (monthType !== 'current-month') {
            dayElement.classList.add(monthType);
        }

        if (this.isSameDay(dayDate, today)) {
            dayElement.classList.add('today');
        }

        if (this.isSameDay(dayDate, this.selectedDate)) {
            dayElement.classList.add('active');
        }

        if (this.hasEventsOnDate(dayDate)) {
            dayElement.classList.add('has-event');
        }

        const ariaLabel = this.isSameDay(dayDate, today)
            ? `Jour ${day} ${this.months[month]} ${year}, aujourd'hui`
            : `Jour ${day} ${this.months[month]} ${year}`;
        dayElement.setAttribute('role', 'button');
        dayElement.setAttribute('aria-label', ariaLabel);
        dayElement.setAttribute('tabindex', '0');

        dayElement.addEventListener('click', () => {
            if (monthType === 'prev-month') {
                this.previousMonth();
                this.selectedDate = dayDate;
                this.activeDay = day;
                this.month = month;
                this.year = year;
            } else if (monthType === 'next-month') {
                this.nextMonth();
                this.selectedDate = dayDate;
                this.activeDay = day;
                this.month = month;
                this.year = year;
            } else {
                this.selectedDate = dayDate;
                this.activeDay = day;
                this.month = month;
                this.year = year;
                this.renderCalendar();
            }

            this.updateSelectedDate();
            this.updateURL();
            this.loadEventsForDate(this.formatDate(this.selectedDate));
            document.getElementById('selectedDateInput').value = this.formatDate(this.selectedDate);
        });

        return dayElement;
    }

    previousMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.month = this.currentDate.getMonth();
        this.year = this.currentDate.getFullYear();
        this.renderCalendar();
    }

    nextMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.month = this.currentDate.getMonth();
        this.year = this.currentDate.getFullYear();
        this.renderCalendar();
    }

    goToToday() {
        this.currentDate = new Date();
        this.selectedDate = new Date();
        this.activeDay = this.selectedDate.getDate();
        this.month = this.selectedDate.getMonth();
        this.year = this.selectedDate.getFullYear();
        this.renderCalendar();
        this.updateSelectedDate();
        this.updateURL();
        this.loadEventsForDate(this.formatDate(this.selectedDate));
        document.getElementById('selectedDateInput').value = this.formatDate(this.selectedDate);
    }

    goToDate() {
        const input = document.getElementById('dateInput').value;
        const parts = input.split('/');

        if (parts.length === 2) {
            const month = parseInt(parts[0]) - 1;
            const year = parseInt(parts[1]);

            if (month >= 0 && month <= 11 && year >= 1900 && year <= 2100) {
                this.currentDate = new Date(year, month, 1);
                this.selectedDate = new Date(year, month, 1);
                this.activeDay = 1;
                this.month = month;
                this.year = year;
                this.renderCalendar();
                this.updateSelectedDate();
                this.updateURL();
                this.loadEventsForDate(this.formatDate(this.selectedDate));
                document.getElementById('selectedDateInput').value = this.formatDate(this.selectedDate);
                document.getElementById('dateInput').value = '';
            } else {
                this.showNotification('Date invalide', 'error');
            }
        } else {
            this.showNotification('Format invalide (mm/yyyy)', 'error');
        }
    }

    formatDateInput(e) {
        let value = e.target.value.replace(/[^0-9/]/g, '');
        if (value.length === 2) {
            value += '/';
        }
        if (value.length > 7) {
            value = value.slice(0, 7);
        }
        if (e.inputType === 'deleteContentBackward' && value.length === 3) {
            value = value.slice(0, 2);
        }
        e.target.value = value;
    }

    updateSelectedDate() {
        const dayName = this.dayNames[this.selectedDate.getDay()];
        const day = this.selectedDate.getDate();
        const month = this.months[this.selectedDate.getMonth()];
        const year = this.selectedDate.getFullYear();

        document.getElementById('selectedDay').textContent = day;
        document.getElementById('selectedDateFull').textContent = `${dayName} ${day} ${month} ${year}`;
    }

    updateURL() {
        const dateStr = this.formatDate(this.selectedDate);
        const url = new URL(window.location);
        url.searchParams.set('date', dateStr);
        window.history.pushState({}, '', url);
    }

    openEventModal() {
        document.getElementById('eventModal').classList.add('active');
        document.getElementById('eventTitle').focus();
    }

    closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
        document.getElementById('eventForm').reset();
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('menuToggle');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        toggle.classList.toggle('active');
    }

    closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('menuToggle');

        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        toggle.classList.remove('active');
    }

    async handleEventSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        formData.append('addEvent', '1');
        formData.set('date', this.formatDate(this.selectedDate));

        try {
            const response = await fetch('treatment/treatment_event.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.closeEventModal();
                this.loadEventsForDate(this.formatDate(this.selectedDate));
                this.loadEvents();
                this.showNotification('Événement ajouté avec succès', 'success');
            } else {
                this.showNotification(data.error || 'Erreur lors de l\'ajout', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Erreur de connexion', 'error');
        }
    }

    async loadEvents() {
        try {
            const response = await fetch('treatment/treatment_event.php');
            const data = await response.json();

            if (Array.isArray(data)) {
                this.events = data.map(event => ({
                    ...event,
                    date: new Date(event.year, event.month - 1, event.day)
                }));
                this.renderCalendar();
            }
        } catch (error) {
            console.error('Erreur lors du chargement des événements:', error);
        }
    }

    async loadEventsForDate(dateStr) {
        try {
            const response = await fetch('treatment/treatment_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `date=${dateStr}`
            });

            const data = await response.json();
            this.displayEvents(data);
        } catch (error) {
            console.error('Erreur lors du chargement des événements:', error);
            this.displayEvents([]);
        }
    }

    displayEvents(events) {
        const eventsList = document.getElementById('eventsList');

        if (events.length === 0) {
            eventsList.innerHTML = `
            <div class="no-events">
                <i class="fas fa-calendar-day"></i>
                <h3>Aucun événement</h3>
                <p>Cliquez sur le bouton + pour ajouter un événement</p>
            </div>
        `;
            return;
        }

        eventsList.innerHTML = events.map(event => {
            const maxLength = 100;
            const description = event.descriptionEvent || ''; // Utiliser une chaîne vide si descriptionEvent est null
            const truncatedDescription = description.length > maxLength
                ? description.substring(0, maxLength) + '...'
                : description;
            const readMoreButton = description.length > maxLength
                ? `<span class="read-more" onclick="toggleDescription(this, event)" role="button" tabindex="0" aria-expanded="false">Lire plus <i class="fas fa-chevron-down"></i></span>`
                : '';
            return `
            <div class="event-item" onclick="confirmDelete(${event.id}, event)">
                <div class="event-header">
                    <div class="event-indicator"></div>
                    <h3 class="event-title">${this.escapeHtml(event.title)}</h3>
                </div>
                <div class="event-time">${this.escapeHtml(event.time.substring(0, 5))}</div>
                <div class="description-container">
                    <div class="event-description" data-full-text="${this.escapeHtml(description)}">
                        ${this.escapeHtml(truncatedDescription)}
                    </div>
                    ${readMoreButton}
                </div>
            </div>
        `;
        }).join('');
    }

    hasEventsOnDate(date) {
        return this.events.some(event => this.isSameDay(event.date, date));
    }

    isSameDay(date1, date2) {
        return date1.getDate() === date2.getDate() &&
            date1.getMonth() === date2.getMonth() &&
            date1.getFullYear() === date2.getFullYear();
    }

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;

        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 20px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    animation: slideInRight 0.3s ease-out;
                }
                .notification-success { background: #10b981; }
                .notification-error { background: #ef4444; }
                .notification-info { background: #3b82f6; }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

function confirmDelete(eventId, event) {
    // Vérifier que le clic ne provient pas du bouton "Lire plus" (de script.js)
    if (event.target.classList.contains('read-more') || event.target.closest('.read-more')) {
        return;
    }

    if (confirm('Voulez-vous vraiment supprimer cet événement ?')) {
        const formData = new URLSearchParams();
        formData.append('eventID', eventId);
        formData.append('deleteEvent', '1');

        fetch('treatment/treatment_deleteEvent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
        })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(data => {
                        throw new Error(data.error || 'Erreur serveur');
                    });
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    calendar.loadEventsForDate(calendar.formatDate(calendar.selectedDate));
                    calendar.loadEvents();
                    calendar.showNotification('Événement supprimé avec succès', 'success');
                } else {
                    calendar.showNotification(data.error || 'Erreur lors de la suppression', 'error');
                }
            })
            .catch(err => {
                console.error('Erreur lors de la suppression de l\'événement :', err);
                calendar.showNotification('Une erreur est survenue : ' + err.message, 'error');
            });
    }
}

function toggleDescription(element, event) {
    event.stopPropagation();
    const descriptionEl = element.previousElementSibling;
    const isExpanded = element.getAttribute('aria-expanded') === 'true';

    if (isExpanded) {
        descriptionEl.textContent = descriptionEl.getAttribute('data-full-text').slice(0, 100) + '...';
        descriptionEl.classList.remove('expanded');
        element.innerHTML = 'Lire plus <i class="fas fa-chevron-down"></i>';
        element.setAttribute('aria-expanded', 'false');
    } else {
        descriptionEl.textContent = descriptionEl.getAttribute('data-full-text');
        descriptionEl.classList.add('expanded');
        element.innerHTML = 'Lire moins <i class="fas fa-chevron-up"></i>';
        element.setAttribute('aria-expanded', 'true');
    }

    const container = descriptionEl.parentElement;
    container.style.height = 'auto';
    const newHeight = descriptionEl.offsetHeight + element.offsetHeight + 10;
    container.style.height = 'css(' + newHeight + ')px';
}

document.addEventListener('DOMContentLoaded', () => {
    calendar = new CalendarApp();
});