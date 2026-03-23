<?php
// ===== CALENDAR.PHP - VERSION OPTIMISÉE =====

session_start();

// Vérification session
if (!isset($_SESSION['id'])) {
    header('Location: /connexion');
    exit;
}

// Configuration
$title = "Mon AgendDAY";
include 'head.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/assets/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="app-container">
        <!-- Menu Toggle -->
        <button class="menu-toggle" id="menuToggle" aria-label="Ouvrir le menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar" aria-label="Menu principal">
            <div class="sidebar-header">
                <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="sidebar-logo">
                <span class="sidebar-brand">AgendDAY</span>
            </div>
            <ul class="sidebar-menu">
                <li><a href="/"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="/contact"><i class="fas fa-envelope"></i> Contact</a></li>
                <li>
                    <button class="theme-toggle-sidebar" id="themeToggleSidebar" aria-label="Changer de thème">
                        <i class="fas fa-moon"></i>
                        <span>Thème sombre</span>
                    </button>
                </li>
                <li><a href="/deconnexion"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>

        <!-- Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Calendrier -->
        <div class="calendar-container">
            <!-- Section Calendrier -->
            <section class="calendar-section">
                <div class="calendar-widget">
                    <!-- Header -->
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
                            <button class="theme-toggle-calendar" id="themeToggleCalendar"
                                aria-label="Changer de thème">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Jours de la semaine -->
                    <div class="calendar-weekdays" id="calendarWeekdays"></div>

                    <!-- Jours du mois -->
                    <div class="calendar-days" id="calendarDays" role="grid"></div>
                </div>
            </section>

            <!-- Section Événements -->
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

        <!-- Bouton Ajouter -->
        <button class="add-event-btn" id="addEventBtn" aria-label="Ajouter un événement">
            <i class="fas fa-plus"></i>
        </button>

        <!-- Modal Événement -->
        <div class="event-modal" id="eventModal" role="dialog" aria-labelledby="modalTitle" aria-modal="true">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Ajouter un événement</h3>
                    <button class="modal-close" id="modalClose" aria-label="Fermer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="eventForm" action="/treatment/treatment_event.php" method="post">
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
    </div>

    <!-- Scripts -->
    <script src="/assets/js/calendar.js"></script>
</body>

</html>