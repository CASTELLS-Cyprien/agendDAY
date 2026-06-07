<button class="menu-toggle" id="menuToggle" aria-label="Ouvrir le menu">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="calendar-container">
    <section class="calendar-section">
        <div class="calendar-widget">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button class="prev-month" id="prevMonth" aria-label="Mois précédent">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2 class="calendar-title" id="calendarTitle">Décembre 2024</h2>
                    <button class="next-month" id="nextMonth" aria-label="Mois suivant">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-header-right">
                    <div class="calendar-quick-nav">
                        <div class="quick-nav-input">
                            <input type="text" class="date-input" id="dateInput" placeholder="mm/yyyy"
                                aria-label="Aller à une date (format mm/yyyy)">
                            <button class="goto-btn" id="gotoBtn">Aller</button>
                        </div>
                        <button class="today-btn" id="todayBtn">Aujourd'hui</button>
                    </div>
                    <button class="theme-toggle-calendar" id="themeToggleCalendar" aria-label="Changer de thème">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>

            <div class="calendar-weekdays" id="calendarWeekdays"></div>
            <div class="calendar-days" id="calendarDays" role="grid"></div>
        </div>
    </section>

    <section class="events-section">
        <div class="events-header">
            <div class="selected-date">
                <div class="selected-day" id="selectedDay">15</div>
                <div class="selected-date-full" id="selectedDateFull">Décembre 2024</div>
            </div>
        </div>

        <div class="events-list" id="eventsList">
            <div class="no-events">
                <i class="fas fa-calendar-day"></i>
                <h3>Aucun événement</h3>
                <p>Cliquez sur le bouton + pour ajouter un événement</p>
            </div>
        </div>
    </section>
</div>

<button class="add-event-btn" id="addEventBtn" aria-label="Ajouter un événement">
    <i class="fas fa-plus"></i>
</button>

<div class="event-modal" id="eventModal" role="dialog" aria-labelledby="modalTitle" aria-modal="true">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Ajouter un événement</h3>
            <button class="modal-close" id="modalClose" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="eventForm" method="post">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($this->csrfToken()) ?>">
            <input type="hidden" name="eventId" id="eventId">
            <input type="hidden" name="date" id="selectedDateInput">

            <div class="form-group">
                <label class="form-label" for="eventTitle">Titre de l'événement</label>
                <input type="text" class="form-input" id="eventTitle" name="title" maxlength="200" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="eventTime">Heure de rappel</label>
                <input type="time" class="form-input" id="eventTime" name="time" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="eventDescription">Description (optionnel)</label>
                <textarea class="form-input form-textarea" id="eventDescription" name="descriptionEvent"
                    maxlength="2000"></textarea>
            </div>

            <div class="modal-actions" id="modalActions">
                <button type="button" class="btn btn-secondary" id="cancelBtn">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Ajouter</button>
            </div>
        </form>
    </div>
</div>
