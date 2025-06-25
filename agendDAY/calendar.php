<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>AgendDAY - Mon Calendrier</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
    
    <link rel="icon" href="assets/images/logo.png">
</head>

<body>
    <?php
    session_start();
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['id'])) {
        header('Location: connexion.php');
        exit;
    }

    // Connexion à la base de données
    try {
        $bdd = new PDO("mysql:host=castelpcyp.mysql.db;dbname=castelpcyp", "castelpcyp", "reRPhrkHNNxoz69TfNKF",[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Récupérer les événements de l'utilisateur
        $userID = $_SESSION['id'];
        $selectedDate = $_GET['date'] ?? date('Y-m-d');
        $sql = "SELECT * FROM events WHERE userID = :userID AND dateEvent = :date ORDER BY time ASC";
        $requete = $bdd->prepare($sql);
        $requete->execute([
            ":userID" => $userID,
            ":date" => $selectedDate
        ]);

        $events = $requete->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
    ?>

    <div class="app-container">
        <!-- Menu Toggle -->
        <button class="menu-toggle" id="menuToggle">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/images/logo.png" alt="AgendDAY" class="sidebar-logo">
                <span class="sidebar-brand">AgendDAY</span>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                <li>
                    <button class="theme-toggle-sidebar" id="themeToggleSidebar" aria-label="Changer de thème">
                        <i class="fas fa-moon"></i>
                        <span>Thème sombre</span>
                    </button>
                </li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Calendar Container -->
        <div class="calendar-container">
            <!-- Calendar Section -->
            <section class="calendar-section">
                <div class="calendar-widget">
                    <!-- Calendar Header -->
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <button class="prev-month" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h2 class="calendar-title" id="calendarTitle">Décembre 2024</h2>
                            <button class="next-month" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="calendar-header-right">
                            <div class="calendar-quick-nav">
                                <div class="quick-nav-input">
                                    <input type="text" class="date-input" id="dateInput" placeholder="mm/yyyy">
                                    <button class="goto-btn" id="gotoBtn">Aller</button>
                                </div>
                                <button class="today-btn" id="todayBtn">Aujourd'hui</button>
                            </div>
                            <button class="theme-toggle-calendar" id="themeToggleCalendar" aria-label="Changer de thème">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Weekdays -->
                    <div class="calendar-weekdays">
                        <div class="weekday">Dim</div>
                        <div class="weekday">Lun</div>
                        <div class="weekday">Mar</div>
                        <div class="weekday">Mer</div>
                        <div class="weekday">Jeu</div>
                        <div class="weekday">Ven</div>
                        <div class="weekday">Sam</div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="calendar-days" id="calendarDays">
                        <!-- Les jours seront générés par JavaScript -->
                    </div>
                </div>
            </section>

            <!-- Events Section -->
            <section class="events-section">
                <div class="events-header">
                    <div class="selected-date">
                        <div class="selected-day" id="selectedDay">15</div>
                        <div class="selected-date-full" id="selectedDateFull">Décembre 2024</div>
                    </div>
                </div>

                <div class="events-list" id="eventsList">
                    <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-item" onclick="confirmDelete(<?= $event['id'] ?>, event)">
                                <div class="event-header">
                                    <div class="event-indicator"></div>
                                    <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                                </div>
                                <div class="event-time"><?= htmlspecialchars($event['time']) ?></div>
                                <div class="event-description">
                                    <?= htmlspecialchars($event['descriptionEvent']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-events">
                            <i class="fas fa-calendar-day"></i>
                            <h3>Aucun événement</h3>
                            <p>Cliquez sur le bouton + pour ajouter un événement</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Add Event Button -->
        <button class="add-event-btn" id="addEventBtn">
            <i class="fas fa-plus"></i>
        </button>

        <!-- Event Modal -->
        <div class="event-modal" id="eventModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Ajouter un événement</h3>
                    <button class="modal-close" id="modalClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="eventForm" action="treatment/treatment_event.php" method="post">
                    <div class="form-group">
                        <label class="form-label" for="eventTitle">Titre de l'événement</label>
                        <input type="text" class="form-input" id="eventTitle" name="title" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="eventTime">Heure de rappel</label>
                        <input type="time" class="form-input" id="eventTime" name="time" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="eventDescription">Description (optionnel)</label>
                        <textarea class="form-input form-textarea" id="eventDescription" name="descriptionEvent"></textarea>
                    </div>

                    <input type="hidden" name="date" id="selectedDateInput">

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">Annuler</button>
                        <button type="submit" class="btn btn-primary" name="addEvent">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/calendar.js"></script>
</body>
</html>