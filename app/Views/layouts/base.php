<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <?php
    $baseUrl  = APP_URL;
    $pages = [
        'accueil' => [
            'title'     => 'AgendDAY — Agenda en ligne gratuit et intuitif',
            'description' => 'AgendDAY est votre agenda en ligne gratuit. Planifiez vos événements, recevez des rappels par email et organisez vos journées depuis n\'importe quel appareil.',
            'canonical' => $baseUrl . '/',
            'og_type'   => 'website',
            'noindex'   => false,
        ],
        'mentions-legales' => [
            'title'     => 'Mentions légales — AgendDAY',
            'description' => 'Mentions légales du site AgendDAY, agenda en ligne hébergé par OVHcloud.',
            'canonical' => $baseUrl . '/mentions-legales',
            'og_type'   => 'website',
            'noindex'   => true,
        ],
        'politique-confidentialite' => [
            'title'     => 'Politique de confidentialité — AgendDAY',
            'description' => 'Politique de confidentialité et de protection des données personnelles d\'AgendDAY.',
            'canonical' => $baseUrl . '/politique-confidentialite',
            'og_type'   => 'website',
            'noindex'   => true,
        ],
    ];
    $meta = $pages[$pageKey ?? ''] ?? [
        'title'     => 'AgendDAY — Agenda en ligne',
        'description' => 'AgendDAY, votre agenda en ligne gratuit.',
        'canonical' => $baseUrl . '/',
        'og_type'   => 'website',
        'noindex'   => false,
    ];
    $previewUrl = $baseUrl . '/assets/images/agendday-preview-light.webp';
    ?>

    <title><?= htmlspecialchars($meta['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">

    <?php if ($meta['noindex']): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <meta name="robots" content="index, follow">
    <?php endif; ?>

    <link rel="canonical" href="<?= htmlspecialchars($meta['canonical']) ?>">

    <meta property="og:type" content="<?= htmlspecialchars($meta['og_type']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($meta['canonical']) ?>">
    <meta property="og:site_name" content="AgendDAY">
    <meta property="og:image" content="<?= $previewUrl ?>">
    <meta property="og:locale" content="fr_FR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta name="twitter:image" content="<?= $previewUrl ?>">

    <?php if (($pageKey ?? '') === 'accueil'): ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebApplication",
            "name": "AgendDAY",
            "url": "<?= $baseUrl ?>",
            "description": "<?= htmlspecialchars($meta['description']) ?>",
            "applicationCategory": "ProductivityApplication",
            "operatingSystem": "Web",
            "offers": { "@type": "Offer", "price": "0", "priceCurrency": "EUR" },
            "author": {
                "@type": "Person",
                "name": "Cyprien Castells",
                "url": "https://www.linkedin.com/in/cyprien-castells-168331370/"
            }
        }
        </script>
    <?php endif; ?>

    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= $this->asset('/assets/css/global.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('/assets/css/home.css') ?>">
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="header-logo" aria-label="Retour à la page d'accueil d'AgendDAY">
                    <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="logo-img" loading="lazy">
                    <span class="header-brand">AgendDAY</span>
                </a>
                <!-- role="navigation" est redondant sur <nav> — supprimé -->
                <nav class="header-nav" aria-label="Navigation principale">
                    <ul class="nav-list">
                        <li><a href="/" class="nav-link">Accueil</a></li>
                        <li><a href="#features" class="nav-link">Fonctionnalités</a></li>
                        <li><a href="#about" class="nav-link">À propos</a></li>
                        <li>
                            <button id="themeToggleHeader" class="theme-toggle-sidebar"
                                aria-label="Basculer entre thème clair et sombre">
                                <i class="fas fa-moon"></i>
                            </button>
                        </li>
                        <?php if ($isLoggedIn ?? false): ?>
                            <li><a href="/deconnexion" class="btn btn-outline nav-cta">Déconnexion</a></li>
                            <li><a href="/calendrier" class="btn btn-primary nav-cta">Mon calendrier</a></li>
                        <?php else: ?>
                            <li><a href="/connexion" class="btn btn-outline nav-cta">Connexion</a></li>
                            <li><a href="/inscription" class="btn btn-primary nav-cta">S'inscrire</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <button class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <?= $content ?>

    <footer class="footer">
        <div class="container">
            <div class="footer-content flex justify-between flex-wrap gap-8">
                <div class="footer-brand">
                    <a href="/" class="header-logo" aria-label="Retour à la page d'accueil d'AgendDAY">
                        <img src="/assets/images/logo.png" alt="Logo AgendDAY" class="logo-img" loading="lazy">
                        <span class="header-brand">AgendDAY</span>
                    </a>
                    <p class="text-sm text-muted mt-2">© <?= date('Y') ?> AgendDAY. Tous droits réservés.</p>
                </div>
                <div class="footer-links">
                    <h4 class="font-semibold text-sm mb-3">Liens utiles</h4>
                    <ul class="text-sm">
                        <li><a href="/contact" class="form-link">Contact</a></li>
                        <li><a href="/mentions-legales" class="form-link">Mentions légales</a></li>
                        <li><a href="/politique-confidentialite" class="form-link">Politique de confidentialité</a></li>
                    </ul>
                </div>
                <div class="footer-social">
                    <h4 class="font-semibold text-sm mb-3">Suivez-nous</h4>
                    <div class="social-icons flex gap-4">
                        <a href="https://www.instagram.com/agendday" class="social-icon" target="_blank"
                            rel="noopener" aria-label="Suivez AgendDAY sur Instagram">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="https://www.linkedin.com/in/cyprien-castells-168331370/" class="social-icon"
                            target="_blank" rel="noopener" aria-label="Suivez moi sur LinkedIn">
                            <i class="fab fa-linkedin-in fa-lg"></i>
                        </a>
                        <a href="https://github.com/CASTELLS-Cyprien/agendDAY" class="social-icon" target="_blank"
                            rel="noopener" aria-label="Suivez moi sur GitHub">
                            <i class="fab fa-github fa-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= $this->asset('/assets/js/theme.js') ?>"></script>
    <script src="<?= $this->asset('/assets/js/home.js') ?>"></script>
</body>

</html>
