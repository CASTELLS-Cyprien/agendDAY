<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon calendrier — AgendDAY</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/png" href="/assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= $this->asset('/assets/css/global.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('/assets/css/calendar.css') ?>">
</head>

<body>
    <div class="app-container">
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

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <?= $content ?>
    </div>

    <script src="<?= $this->asset('/assets/js/calendar.js') ?>"></script>
</body>

</html>
