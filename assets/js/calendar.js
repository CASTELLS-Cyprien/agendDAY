// ===== CALENDRIER OPTIMISÉ =====

class CalendarApp {
    constructor() {
        // Dates
        this.currentDate = new Date();
        this.selectedDate = new Date();
        
        // Configuration
        this.config = {
            CACHE_EXPIRY: 5 * 60 * 1000, // 5 minutes
            NOTIFICATION_DURATION: 3000,
            DEBOUNCE_DELAY: 300,
            MAX_DESCRIPTION_LENGTH: 100,
            RETRY_ATTEMPTS: 3,
            locale: 'fr-FR',
            firstDayOfWeek: 1 // Lundi
        };
        
        // Cache et données
        this.events = [];
        this.eventsCache = new Map();
        this.debounceTimers = {};
        
        // Génération noms jours/mois
        this.dayNames = this.generateDayNames();
        this.shortDayNames = this.dayNames.map(d => d.slice(0, 3).toUpperCase());
        this.months = this.generateMonthNames();
        
        this.init();
    }

    init() {
        this.initTheme();
        this.bindEvents();
        this.loadEvents();
        this.setInitialDate();
        this.renderCalendar();
        this.updateSelectedDate();
    }

    // === THÈME ===
    initTheme() {
        try {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            this.updateThemeIcons(savedTheme);
        } catch (e) {
            console.warn('localStorage non disponible:', e);
            document.documentElement.setAttribute('data-theme', 'light');
        }
    }

    updateThemeIcons(theme) {
        document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar').forEach(toggle => {
            const icon = toggle.querySelector('i');
            const text = toggle.querySelector('span');
            if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            if (text) text.textContent = theme === 'dark' ? 'Thème clair' : 'Thème sombre';
        });
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        
        try {
            localStorage.setItem('theme', newTheme);
        } catch (e) {
            console.warn('Impossible de sauvegarder le thème:', e);
        }
        
        this.updateThemeIcons(newTheme);
    }

    // === ÉVÉNEMENTS ===
    bindEvents() {
        // Navigation calendrier
        document.getElementById('prevMonth').addEventListener('click', () => this.changeMonth(-1));
        document.getElementById('nextMonth').addEventListener('click', () => this.changeMonth(1));
        document.getElementById('todayBtn').addEventListener('click', () => this.goToToday());
        document.getElementById('gotoBtn').addEventListener('click', () => this.goToDate());

        // Modal événement
        document.getElementById('addEventBtn').addEventListener('click', () => this.openEventModal());
        document.getElementById('modalClose').addEventListener('click', () => this.closeEventModal());
        document.getElementById('cancelBtn').addEventListener('click', () => this.closeEventModal());
        document.getElementById('eventForm').addEventListener('submit', (e) => this.handleEventSubmit(e));
        
        document.getElementById('eventModal').addEventListener('click', (e) => {
            if (e.target.id === 'eventModal') this.closeEventModal();
        });

        // Sidebar
        document.getElementById('menuToggle').addEventListener('click', () => this.toggleSidebar());
        document.getElementById('sidebarOverlay').addEventListener('click', () => this.closeSidebar());

        // Thème
        document.querySelectorAll('#themeToggleSidebar, #themeToggleCalendar')
            .forEach(toggle => toggle.addEventListener('click', () => this.toggleTheme()));

        // Input date avec debounce
        const dateInput = document.getElementById('dateInput');
        dateInput.addEventListener('input', (e) => {
            this.formatDateInput(e);
            if (e.target.value.length === 7) {
                this.debounce(() => this.goToDate(), 500)();
            }
        });
        dateInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.goToDate();
        });

        // Clavier
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }

    handleKeyboard(e) {
        if (e.key === 'Escape') {
            this.closeEventModal();
            this.closeSidebar();
            return;
        }

        // Navigation au clavier dans le calendrier
        if (!document.activeElement.closest('.calendar-day')) return;
        
        const newDate = new Date(this.selectedDate);
        const dayOffsets = {
            'ArrowLeft': -1,
            'ArrowRight': 1,
            'ArrowUp': -7,
            'ArrowDown': 7
        };

        if (dayOffsets[e.key]) {
            e.preventDefault();
            newDate.setDate(newDate.getDate() + dayOffsets[e.key]);
            this.selectDate(newDate);
        }
    }

    // === UTILITAIRES ===
    debounce(func, delay = this.config.DEBOUNCE_DELAY) {
        return (...args) => {
            const key = func.toString();
            clearTimeout(this.debounceTimers[key]);
            this.debounceTimers[key] = setTimeout(() => func.apply(this, args), delay);
        };
    }

    async fetchWithRetry(url, options = {}, retries = this.config.RETRY_ATTEMPTS) {
        for (let i = 0; i < retries; i++) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return await response.json();
            } catch (error) {
                if (i === retries - 1) throw error;
                await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
            }
        }
    }

    generateDayNames() {
        const baseDate = new Date(2025, 0, 6); 
        const formatter = new Intl.DateTimeFormat(this.config.locale, { weekday: 'long' });
        return Array.from({ length: 7 }, (_, i) => {
            const date = new Date(baseDate);
            date.setDate(baseDate.getDate() + i);
            return formatter.format(date);
        });
    }

    generateMonthNames() {
        const formatter = new Intl.DateTimeFormat(this.config.locale, { month: 'long' });
        return Array.from({ length: 12 }, (_, i) => formatter.format(new Date(2025, i, 1)));
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

    // === INITIALISATION DATE ===
    setInitialDate() {
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');

        if (dateParam) {
            const date = new Date(dateParam);
            if (!isNaN(date.getTime())) {
                this.selectDate(date, false);
                return;
            }
        }

        this.selectDate(new Date(), false);
    }

    // === NAVIGATION ===
    changeMonth(delta) {
        this.currentDate.setMonth(this.currentDate.getMonth() + delta);
        this.renderCalendar();
    }

    goToToday() {
        this.selectDate(new Date());
    }

    goToDate() {
        const input = document.getElementById('dateInput').value;
        const parts = input.split('/');

        if (parts.length !== 2) {
            this.showNotification('Format invalide (mm/yyyy)', 'error');
            return;
        }

        const month = parseInt(parts[0]) - 1;
        const year = parseInt(parts[1]);

        if (month < 0 || month > 11 || year < 1900 || year > 2100) {
            this.showNotification('Date invalide', 'error');
            return;
        }

        this.selectDate(new Date(year, month, 1));
        document.getElementById('dateInput').value = '';
    }

    selectDate(date, updateUrl = true) {
        this.selectedDate = date;
        this.currentDate = new Date(date.getFullYear(), date.getMonth(), 1);
        
        this.renderCalendar();
        this.updateSelectedDate();
        
        if (updateUrl) this.updateURL();
        
        const dateStr = this.formatDate(date);
        this.loadEventsForDate(dateStr);
        document.getElementById('selectedDateInput').value = dateStr;
    }

    formatDateInput(e) {
        let value = e.target.value.replace(/[^0-9/]/g, '');
        if (value.length === 2 && e.inputType !== 'deleteContentBackward') value += '/';
        if (value.length > 7) value = value.slice(0, 7);
        if (e.inputType === 'deleteContentBackward' && value.length === 3) {
            value = value.slice(0, 2);
        }
        e.target.value = value;
    }

    updateSelectedDate() {
        const dayIndex = (this.selectedDate.getDay() + 6) % 7; 
        const dayName = this.dayNames[dayIndex];
        const day = this.selectedDate.getDate();
        const month = this.months[this.selectedDate.getMonth()];
        const year = this.selectedDate.getFullYear();

        document.getElementById('selectedDay').textContent = day;
        document.getElementById('selectedDateFull').textContent = `${dayName} ${day} ${month} ${year}`;
    }

    updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('date', this.formatDate(this.selectedDate));
        window.history.pushState({}, '', url);
    }

    // === RENDU CALENDRIER ===
    renderCalendar() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();

        document.getElementById('calendarTitle').textContent = `${this.months[month]} ${year}`;

        const firstDay = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        const startDayOfWeek = (firstDay.getDay() + 6) % 7;

        const calendarDays = document.getElementById('calendarDays');
        const calendarWeekdays = document.getElementById('calendarWeekdays');

        calendarDays.innerHTML = '';
        calendarWeekdays.innerHTML = this.shortDayNames.map(name => `<div class="weekday">${name}</div>`).join('');

        // Mois précédent
        for (let i = startDayOfWeek; i > 0; i--) {
            const day = daysInPrevMonth - i + 1;
            calendarDays.appendChild(this.createDayElement(day, 'prev-month', year, month - 1));
        }

        // Mois courant
        for (let day = 1; day <= daysInMonth; day++) {
            calendarDays.appendChild(this.createDayElement(day, 'current-month', year, month));
        }

        // Mois suivant
        const totalDays = calendarDays.children.length;
        const remaining = 42 - totalDays;
        for (let day = 1; day <= remaining; day++) {
            calendarDays.appendChild(this.createDayElement(day, 'next-month', year, month + 1));
        }

        // Délégation d'événements
        calendarDays.onclick = (e) => {
            const dayElement = e.target.closest('.calendar-day');
            if (!dayElement) return;
            
            const day = parseInt(dayElement.dataset.day);
            const month = parseInt(dayElement.dataset.month);
            const year = parseInt(dayElement.dataset.year);
            const monthType = dayElement.dataset.monthType;
            
            this.handleDayClick(day, month, year, monthType);
        };
    }

    createDayElement(day, monthType, year, month) {
        const dayDate = new Date(year, month, day);
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${monthType}`;
        dayElement.textContent = day;

        // Stockage données
        dayElement.dataset.day = day;
        dayElement.dataset.month = month;
        dayElement.dataset.year = year;
        dayElement.dataset.monthType = monthType;

        // Classes CSS
        const today = new Date();
        if (this.isSameDay(dayDate, today)) dayElement.classList.add('today');
        if (this.isSameDay(dayDate, this.selectedDate)) dayElement.classList.add('active');
        if (this.hasEventsOnDate(dayDate)) dayElement.classList.add('has-event');

        // Accessibilité — on utilise dayDate (date normalisée) pour éviter months[-1] ou months[12]
        const realMonth = dayDate.getMonth();
        const realYear  = dayDate.getFullYear();
        const ariaLabel = this.isSameDay(dayDate, today)
            ? `Jour ${day} ${this.months[realMonth]} ${realYear}, aujourd'hui`
            : `Jour ${day} ${this.months[realMonth]} ${realYear}`;
        dayElement.setAttribute('role', 'button');
        dayElement.setAttribute('aria-label', ariaLabel);
        dayElement.setAttribute('tabindex', '0');

        return dayElement;
    }

    handleDayClick(day, month, year, monthType) {
        if (monthType === 'prev-month') {
            this.changeMonth(-1);
        } else if (monthType === 'next-month') {
            this.changeMonth(1);
        }
        
        this.selectDate(new Date(year, month, day));
    }

    hasEventsOnDate(date) {
        return this.events.some(event => this.isSameDay(event.date, date));
    }

    // === ÉVÉNEMENTS ===
    async loadEvents() {
        try {
            const data = await this.fetchWithRetry('/api/events');
            
            if (Array.isArray(data)) {
                this.events = data.map(event => ({
                    ...event,
                    date: new Date(event.year, event.month - 1, event.day)
                }));
                this.renderCalendar();
            }
        } catch (error) {
            console.error('Erreur chargement événements:', error);
            this.showNotification('Impossible de charger les événements', 'error');
        }
    }

    async loadEventsForDate(dateStr) {
        // Vérifier cache
        const cached = this.eventsCache.get(dateStr);
        if (cached && Date.now() - cached.timestamp < this.config.CACHE_EXPIRY) {
            this.displayEvents(cached.data);
            return;
        }

        try {
            const response = await fetch('/api/events/by-date', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `date=${dateStr}`
            });

            const data = await response.json();
            
            // Mise en cache
            this.eventsCache.set(dateStr, {
                data,
                timestamp: Date.now()
            });
            
            this.displayEvents(data);
        } catch (error) {
            console.error('Erreur:', error);
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
                </div>`;
            eventsList.onclick = null;
            return;
        }

        eventsList.innerHTML = events.map(event => {
            const description = event.descriptionEvent || '';
            const maxLength   = this.config.MAX_DESCRIPTION_LENGTH;
            const truncated   = description.length > maxLength
                ? description.substring(0, maxLength) + '...'
                : description;
            const readMore = description.length > maxLength
                ? `<button type="button" class="read-more" aria-expanded="false">Lire plus <i class="fas fa-chevron-down"></i></button>`
                : '';

            return `
                <div class="event-item" data-event-id="${event.id}">
                    <div class="event-header">
                        <div class="event-indicator"></div>
                        <h3 class="event-title">${this.escapeHtml(event.title)}</h3>
                    </div>
                    <div class="event-time">${this.escapeHtml(event.time.substring(0, 5))}</div>
                    <div class="description-container">
                        <div class="event-description" data-full-text="${this.escapeHtml(description)}">
                            ${this.escapeHtml(truncated)}
                        </div>
                        ${readMore}
                    </div>
                </div>`;
        }).join('');

        // onclick = affectation directe : remplace le handler précédent à chaque appel,
        // contrairement à addEventListener qui accumulerait les listeners sur la liste.
        eventsList.onclick = (e) => {
            const readMoreBtn = e.target.closest('.read-more');
            if (readMoreBtn) {
                e.stopPropagation();
                this.toggleDescription(readMoreBtn);
                return;
            }
            const item = e.target.closest('.event-item');
            if (!item) return;
            const eventId   = item.dataset.eventId;
            const eventData = events.find(ev => ev.id == eventId);
            if (eventData) this.openEditModal(eventData);
        };
    }

    async handleEventSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const isUpdate = !!formData.get('eventId');

        formData.set('date', this.formatDate(this.selectedDate));

        try {
            const url = isUpdate ? '/api/events/update' : '/api/events';
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.eventsCache.clear(); // Invalider cache
                this.closeEventModal();
                this.loadEventsForDate(this.formatDate(this.selectedDate));
                this.loadEvents();
                this.showNotification(isUpdate ? 'Événement modifié' : 'Événement ajouté', 'success');
            } else {
                this.showNotification(data.error || 'Erreur', 'error');
            }
        } catch (error) {
            this.showNotification('Erreur de connexion', 'error');
        }
    }

    // === MODAL ===
    openEventModal() {
        document.getElementById('eventModal').classList.add('active');
        document.getElementById('eventTitle').focus();
    }

    closeEventModal() {
        const modal = document.getElementById('eventModal');
        modal.classList.remove('active');
        document.getElementById('eventForm').reset();
        document.getElementById('modalTitle').textContent = 'Ajouter un événement';
        document.getElementById('submitBtn').textContent = 'Ajouter';
        document.getElementById('submitBtn').name = 'addEvent';
        document.getElementById('eventId').value = '';
        
        const deleteBtn = document.getElementById('deleteBtn');
        if (deleteBtn) deleteBtn.remove();
    }

    openEditModal(eventData) {
        document.getElementById('eventId').value = eventData.id;
        document.getElementById('eventTitle').value = eventData.title;
        document.getElementById('eventTime').value = eventData.time.substring(0, 5);
        document.getElementById('eventDescription').value = eventData.descriptionEvent || '';
        document.getElementById('selectedDateInput').value = this.formatDate(this.selectedDate);

        document.getElementById('modalTitle').textContent = "Modifier l'événement";
        document.getElementById('submitBtn').textContent = 'Modifier';
        document.getElementById('submitBtn').name = 'updateEvent';

        // Bouton supprimer
        if (!document.getElementById('deleteBtn')) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.id = 'deleteBtn';
            deleteBtn.className = 'btn btn-danger';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Supprimer';
            deleteBtn.onclick = () => this.confirmAndDelete(eventData.id);
            
            const actions = document.getElementById('modalActions');
            actions.insertBefore(deleteBtn, actions.firstChild.nextSibling);
        }

        document.getElementById('eventModal').classList.add('active');
        document.getElementById('eventTitle').focus();
    }

    async confirmAndDelete(eventId) {
        if (!confirm('Supprimer cet événement ? Cette action est irréversible.')) return;

        try {
            const response = await fetch('/api/events/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `eventID=${eventId}`
            });

            const data = await response.json();
            if (data.success) {
                this.eventsCache.clear();
                this.closeEventModal();
                this.loadEventsForDate(this.formatDate(this.selectedDate));
                this.loadEvents();
                this.showNotification('Événement supprimé', 'success');
            } else {
                this.showNotification(data.error || 'Erreur', 'error');
            }
        } catch (error) {
            this.showNotification('Erreur réseau', 'error');
        }
    }

    // === SIDEBAR ===
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('menuToggle');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        toggle.classList.toggle('active');
    }

    closeSidebar() {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('sidebarOverlay').classList.remove('active');
        document.getElementById('menuToggle').classList.remove('active');
    }

    // === NOTIFICATIONS ===
    showNotification(message, type = 'info') {
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

        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle'
        };

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }, this.config.NOTIFICATION_DURATION);
    }

    // === DESCRIPTION EXTENSIBLE ===
    toggleDescription(btn) {
        const descriptionEl = btn.previousElementSibling;
        const isExpanded    = btn.getAttribute('aria-expanded') === 'true';

        if (isExpanded) {
            const fullText = descriptionEl.getAttribute('data-full-text');
            descriptionEl.textContent = fullText.slice(0, this.config.MAX_DESCRIPTION_LENGTH) + '...';
            btn.innerHTML = 'Lire plus <i class="fas fa-chevron-down"></i>';
            btn.setAttribute('aria-expanded', 'false');
        } else {
            descriptionEl.textContent = descriptionEl.getAttribute('data-full-text');
            btn.innerHTML = 'Lire moins <i class="fas fa-chevron-up"></i>';
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    // === NETTOYAGE ===
    destroy() {
        Object.values(this.debounceTimers).forEach(timer => clearTimeout(timer));
        this.eventsCache.clear();
    }
}

// === INITIALISATION ===
document.addEventListener('DOMContentLoaded', () => {
    const calendar = new CalendarApp();
    // Exposition optionnelle pour le débogage en console
    window.__calendar = calendar;
});