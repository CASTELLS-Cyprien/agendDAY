<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <?php
    // ============================================================
    // SEO — Métadonnées dynamiques par page
    // ============================================================
    $base_url = 'https://agendday.castells-cyprien.ovh';
    $site_name = 'AgendDAY';
    $logo_url = $base_url . '/assets/images/logo.png';
    $preview_url = $base_url . '/assets/images/agendday-preview-light.webp';

    // Définir les métadonnées selon la page
    $pages = [
        'accueil' => [
            'title' => 'AgendDAY — Agenda en ligne gratuit et intuitif',
            'description' => 'AgendDAY est votre agenda en ligne gratuit. Planifiez vos événements, recevez des rappels par email et organisez vos journées depuis n\'importe quel appareil.',
            'canonical' => $base_url . '/',
            'og_type' => 'website',
        ],
        'Connexion' => [
            'title' => 'Connexion — AgendDAY',
            'description' => 'Connectez-vous à votre agenda en ligne AgendDAY pour gérer vos événements et rappels.',
            'canonical' => $base_url . '/connexion',
            'og_type' => 'website',
            'noindex' => true,
        ],
        'Inscription' => [
            'title' => 'Créer un compte — AgendDAY',
            'description' => 'Inscrivez-vous gratuitement sur AgendDAY et commencez à organiser votre emploi du temps dès aujourd\'hui.',
            'canonical' => $base_url . '/inscription',
            'og_type' => 'website',
        ],
        'Mon AgendDAY' => [
            'title' => 'Mon calendrier — AgendDAY',
            'description' => 'Gérez vos événements et rappels depuis votre calendrier personnel AgendDAY.',
            'canonical' => $base_url . '/calendrier',
            'og_type' => 'website',
            'noindex' => true,
        ],
        'Contact' => [
            'title' => 'Contact — AgendDAY',
            'description' => 'Contactez l\'équipe AgendDAY pour toute question ou suggestion concernant notre agenda en ligne.',
            'canonical' => $base_url . '/contact',
            'og_type' => 'website',
        ],
        'Mentions Légales' => [
            'title' => 'Mentions légales — AgendDAY',
            'description' => 'Mentions légales du site AgendDAY, agenda en ligne hébergé par OVHcloud.',
            'canonical' => $base_url . '/mentions-legales',
            'og_type' => 'website',
            'noindex' => true,
        ],
        'Politique de Confidentialité' => [
            'title' => 'Politique de confidentialité — AgendDAY',
            'description' => 'Politique de confidentialité et de protection des données personnelles d\'AgendDAY.',
            'canonical' => $base_url . '/politique-confidentialite',
            'og_type' => 'website',
            'noindex' => true,
        ],
        'Mot de Passe Oublié' => [
            'title' => 'Mot de passe oublié — AgendDAY',
            'description' => 'Réinitialisez votre mot de passe AgendDAY.',
            'canonical' => $base_url . '/mot-de-passe-oublie',
            'og_type' => 'website',
            'noindex' => true,
        ],
    ];

    $meta = $pages[$title] ?? [
        'title' => $site_name . ' — Agenda en ligne',
        'description' => 'AgendDAY, votre agenda en ligne gratuit.',
        'canonical' => $base_url . '/',
        'og_type' => 'website',
    ];
    ?>

    <title><?= htmlspecialchars($meta['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">

    <?php if (!empty($meta['noindex'])): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <meta name="robots" content="index, follow">
    <?php endif; ?>

    <!-- Canonical -->
    <link rel="canonical" href="<?= htmlspecialchars($meta['canonical']) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= htmlspecialchars($meta['og_type']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($meta['canonical']) ?>">
    <meta property="og:site_name" content="<?= $site_name ?>">
    <meta property="og:image" content="<?= $preview_url ?>">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta name="twitter:image" content="<?= $preview_url ?>">

    <!-- Structured Data (page d'accueil uniquement) -->
    <?php if ($title === 'accueil'): ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebApplication",
            "name": "AgendDAY",
            "url": "<?= $base_url ?>",
            "description": "<?= htmlspecialchars($meta['description']) ?>",
            "applicationCategory": "ProductivityApplication",
            "operatingSystem": "Web",
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "EUR"
            },
            "author": {
                "@type": "Person",
                "name": "Cyprien Castells",
                "url": "https://www.linkedin.com/in/cyprien-castells-168331370/"
            }
        }
        </script>
    <?php endif; ?>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/calendar.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/forms.css">

    <!-- reCAPTCHA (contact uniquement) -->
    <?php if ($title === 'Contact'): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>