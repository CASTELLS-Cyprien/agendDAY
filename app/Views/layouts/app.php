<?php
/** Gabarit des pages connectées (calendrier, préférences). */
$userName   = (string) ($user['nomUtilisateur'] ?? '');
$userEmail  = (string) ($user['email'] ?? '');
$initial    = mb_strtoupper(mb_substr($userName !== '' ? $userName : $userEmail, 0, 1));
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="<?= $this->asset('/assets/js/theme-init.js') ?>"></script>

    <title><?= htmlspecialchars($metaTitle ?? 'AgendDAY') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($this->csrfToken()) ?>">
    <meta name="theme-color" content="#2554d4">

    <link rel="icon" type="image/png" href="/assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= $this->asset('/assets/css/global.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('/assets/css/app.css') ?>">
    <?php if ($activePage === 'calendar'): ?>
        <link rel="stylesheet" href="<?= $this->asset('/assets/css/calendar.css') ?>">
    <?php endif; ?>
</head>

<body class="app-body">
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <header class="topbar">
        <a class="topbar__brand" href="/calendrier">
            <img src="/assets/images/logo.png" alt="" width="28" height="28">
            <span>AgendDAY</span>
        </a>

        <nav class="topbar__nav" aria-label="Navigation principale">
            <a href="/calendrier" class="topbar__link" <?= $activePage === 'calendar' ? 'aria-current="page"' : '' ?>>
                <i class="fas fa-calendar-days" aria-hidden="true"></i> Calendrier
            </a>
            <a href="/parametres" class="topbar__link" <?= $activePage === 'settings' ? 'aria-current="page"' : '' ?>>
                <i class="fas fa-sliders" aria-hidden="true"></i> Préférences
            </a>
        </nav>

        <div class="topbar__actions">
            <div class="topbar__item">
                <button type="button" class="btn btn-ghost btn-icon notif-toggle" id="notifToggle"
                    aria-expanded="false" aria-controls="notifPanel" aria-label="Notifications">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="notif-toggle__badge" id="notifBadge" aria-hidden="true" hidden></span>
                </button>
                <section class="popover notif-panel" id="notifPanel" aria-labelledby="notifTitle" hidden>
                    <header class="popover__header">
                        <h2 class="popover__title" id="notifTitle">Notifications</h2>
                        <button type="button" class="btn btn-ghost btn-sm" id="notifMarkAll" disabled>Tout marquer comme lu</button>
                    </header>
                    <p class="alert alert--error notif-panel__error" id="notifError" role="alert" hidden></p>
                    <ul class="notif-list" id="notifList"></ul>
                    <p class="notif-panel__empty" id="notifEmpty" hidden>
                        Aucune notification pour le moment. Vos rappels apparaîtront ici au moment prévu.
                    </p>
                    <footer class="popover__footer">
                        <a href="/parametres#notifications">Préférences de notification</a>
                    </footer>
                </section>
            </div>

            <div class="topbar__item">
                <button type="button" class="usermenu-toggle" id="userMenuToggle" aria-expanded="false" aria-controls="userMenu">
                    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
                    <span class="usermenu-toggle__name"><?= htmlspecialchars($userName) ?></span>
                    <span class="sr-only">— menu du compte</span>
                    <i class="fas fa-chevron-down usermenu-toggle__chevron" aria-hidden="true"></i>
                </button>
                <div class="popover usermenu" id="userMenu" hidden>
                    <div class="usermenu__identity">
                        <strong><?= htmlspecialchars($userName) ?></strong>
                        <span><?= htmlspecialchars($userEmail) ?></span>
                    </div>
                    <ul class="menu-list">
                        <li><a href="/calendrier"><i class="fas fa-calendar-days" aria-hidden="true"></i> Calendrier</a></li>
                        <li><a href="/parametres"><i class="fas fa-sliders" aria-hidden="true"></i> Préférences</a></li>
                        <li>
                            <button type="button" data-theme-toggle>
                                <i class="fas fa-moon" aria-hidden="true"></i> <span data-theme-label>Thème sombre</span>
                            </button>
                        </li>
                        <li><a href="/contact"><i class="fas fa-envelope" aria-hidden="true"></i> Contact</a></li>
                        <li class="menu-list__separator">
                            <form method="post" action="/deconnexion">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">
                                <button type="submit"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Se déconnecter</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="app-main" tabindex="-1">
        <?= $content ?>
    </main>

    <script src="<?= $this->asset('/assets/js/core.js') ?>"></script>
    <script src="<?= $this->asset('/assets/js/theme.js') ?>"></script>
    <script src="<?= $this->asset('/assets/js/notifications.js') ?>"></script>
    <?php if ($activePage === 'calendar'): ?>
        <script src="<?= $this->asset('/assets/js/calendar.js') ?>"></script>
    <?php endif; ?>
</body>

</html>
