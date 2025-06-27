<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>AgendDAY - Agenda en ligne simple et intuitif</title>
    <meta name="description"
        content="Organisez votre emploi du temps avec AgendDAY, un agenda en ligne intuitif et élégant. Essayez gratuitement dès aujourd'hui !">
    <meta property="og:title" content="AgendDAY - Agenda en ligne intuitif">
    <meta property="og:description"
        content="Planifiez vos journées avec AgendDAY, un outil élégant et simple pour gérer votre temps. Essayez maintenant !">
    <meta property="og:image" content="https://www.agendday.com/assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="icon" href="assets/images/logo.png">
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="header-logo" aria-label="Retour à la page d'accueil d'AgendDAY">
                    <img src="assets/images/logo.png" alt="Logo AgendDAY" class="logo-img" loading="lazy">
                    <span class="header-brand">AgendDAY</span>
                </a>
                <nav class="header-nav" aria-label="Navigation principale" role="navigation">
                    <ul class="nav-list">
                        <li><a href="index.php" class="nav-link" aria-current="page">Accueil</a></li>
                        <li><a href="#features" class="nav-link">Fonctionnalités</a></li>
                        <li><a href="#about" class="nav-link">À propos</a></li>
                        <li>
                            <button id="themeToggleHeader" class="theme-toggle-sidebar"
                                aria-label="Basculer entre thème clair et sombre">
                                <i class="fas fa-moon"></i>
                            </button>
                        </li>
                        <?php
                        if (isset($_SESSION['id'])) {
                            echo '<li><a href="logout.php" class="btn btn-outline nav-cta">Déconnexion</a></li>';
                            echo '<li><a href="calendar.php" class="btn btn-primary nav-cta">Mon calendrier</a></li>';
                        } else {
                            echo '<li><a href="connexion.php" class="btn btn-outline nav-cta">Connexion</a></li>';
                            echo '<li><a href="inscription.php" class="btn btn-primary nav-cta">S\'inscrire</a></li>';
                        }
                        ?>
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

    <section class="section hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="section-title animate-slide-up">Planifiez votre quotidien avec simplicité</h1>
                <p class="section-subtitle animate-slide-up">AgendDAY, votre agenda en ligne, vous aide à organiser vos
                    journées avec une interface intuitive pour une gestion du temps optimale.</p>
                <div class="hero-cta flex justify-center gap-4 animate-slide-up">
                    <a href="inscription.php" class="btn btn-primary btn-large">Essayer maintenant</a>
                    <a href="#features" class="btn btn-outline btn-large">En savoir plus</a>
                </div>
                <div class="hero-image animate-fade-in">
                    <picture>
                        <img id="heroImage" src="assets/images/agendday-preview-light.webp"
                            alt="Aperçu de l'agenda en ligne AgendDAY en mode clair" class="rounded-lg shadow-lg"
                            loading="lazy">
                    </picture>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section features-section">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title animate-slide-up">Pourquoi choisir AgendDAY ?</h2>
                <p class="section-subtitle animate-slide-up">Des outils pensés pour simplifier votre organisation
                    quotidienne.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <h3 class="feature-title">Planification fluide</h3>
                    <p class="feature-description">Ajoutez, modifiez et suivez vos événements en un clin d'œil grâce à
                        une <a href="calendar.php">interface intuitive</a>.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon">
                        <i class="fas fa-adjust fa-2x"></i>
                    </div>
                    <h3 class="feature-title">Changement de thème</h3>
                    <p class="feature-description">Passez du mode clair au mode sombre pour une expérience adaptée à
                        votre style.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt fa-2x"></i>
                    </div>
                    <h3 class="feature-title">Toujours accessible</h3>
                    <p class="feature-description">Gérez votre agenda depuis n'importe quel appareil, où que vous soyez.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section testimonials-section">
        <div class="container">
            <h2 class="section-title animate-slide-up">Ce que disent nos utilisateurs</h2>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="card animate-slide-up">
                    <p class="feature-description">"AgendDAY a transformé ma gestion du temps. L’interface est simple et
                        efficace !" - Marie, étudiante</p>
                </div>
                <div class="card animate-slide-up">
                    <p class="feature-description">"Un agenda en ligne parfait pour mon équipe. La synchronisation est
                        un vrai plus." - Paul, manager</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="section about-section">
        <div class="container">
            <div class="about-content text-center">
                <div class="about-icon animate-fade-in">
                    <i class="fas fa-calendar-alt fa-3x" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="section-title animate-slide-up">À propos d'AgendDAY</h2>
                <p class="section-subtitle animate-slide-up">Simplifiez votre quotidien avec élégance</p>
                <div class="about-text max-w-2xl mx-auto">
                    <p class="animate-slide-up mb-6">AgendDAY est une plateforme intuitive conçue pour organiser votre
                        emploi du temps sans effort. Que vous soyez étudiant, professionnel ou parent occupé, nous vous
                        aidons à planifier chaque journée avec clarté et efficacité.</p>
                    <div class="about-features grid md:grid-cols-2 gap-6 animate-slide-up">
                        <div class="feature-item">
                            <i class="fas fa-check-circle fa-lg"
                                style="color: var(--primary-color); margin-bottom: var(--spacing-2);"></i>
                            <p class="text-sm"><a href="connexion.php">Interface intuitive</a> et personnalisable</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-sync-alt fa-lg"
                                style="color: var(--primary-color); margin-bottom: var(--spacing-2);"></i>
                            <p class="text-sm">Synchronisation sur tous vos appareils</p>
                        </div>
                    </div>
                    <a href="inscription.php" class="btn btn-primary btn-large mt-8 animate-slide-up">Commencer
                        maintenant</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section cta-section">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="section-title animate-slide-up">Transformez votre quotidien dès aujourd'hui</h2>
                <p class="section-subtitle animate-slide-up">Rejoignez des milliers d'utilisateurs qui planifient mieux
                    avec AgendDAY.</p>
                <a href="inscription.php" class="btn btn-primary btn-large animate-slide-up">S'inscrire maintenant</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content flex justify-between flex-wrap gap-8">
                <div class="footer-brand">
                    <a href="index.php" class="header-logo" aria-label="Retour à la page d'accueil d'AgendDAY">
                        <img src="assets/images/logo.png" alt="Logo AgendDAY" class="logo-img" loading="lazy">
                        <span class="header-brand">AgendDAY</span>
                    </a>
                    <p class="text-sm text-muted mt-2">© 2025 AgendDAY. Tous droits réservés.</p>
                </div>
                <div class="footer-links">
                    <h4 class="font-semibold text-sm mb-3">Liens utiles</h4>
                    <ul class="text-sm">
                        <li><a href="contact.php" class="form-link">Contact</a></li>
                        <li><a href="mention_legales.php" class="form-link">Mentions légales</a></li>
                        <li><a href="politique_de_confidentialite.php" class="form-link">Politiques de
                                confidentialité</a></li>
                    </ul>
                </div>
                <div class="footer-social">
                    <h4 class="font-semibold text-sm mb-3">Suivez-nous</h4>
                    <div class="social-icons flex gap-4">
                        <a href="https://www.instagram.com/agendday" class="social-icon" target="_blank" rel="noopener"
                            aria-label="Suivez AgendDAY sur Instagram">
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

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/home.js"></script>
</body>

</html>