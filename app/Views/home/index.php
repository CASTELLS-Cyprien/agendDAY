<main>
    <section class="section hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="section-title animate-slide-up">Planifiez votre quotidien avec simplicité</h1>
                <p class="section-subtitle animate-slide-up">AgendDAY, votre agenda en ligne : visualisez votre mois,
                    ajoutez un événement en quelques secondes et recevez un rappel au bon moment.</p>
                <div class="hero-cta flex justify-center gap-4 animate-slide-up">
                    <?php if ($isLoggedIn ?? false): ?>
                        <a href="/calendrier" class="btn btn-primary btn-large">Ouvrir mon calendrier</a>
                    <?php else: ?>
                        <a href="/inscription" class="btn btn-primary btn-large">Créer un compte gratuit</a>
                    <?php endif; ?>
                    <a href="#features" class="btn btn-outline btn-large">Découvrir les fonctionnalités</a>
                </div>
                <div class="hero-image animate-fade-in">
                    <img id="heroImage" src="/assets/images/agendday-preview-light.webp"
                        alt="Aperçu du calendrier AgendDAY en thème clair"
                        class="rounded-lg shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section features-section">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title animate-slide-up">Ce que vous pouvez faire avec AgendDAY</h2>
                <p class="section-subtitle animate-slide-up">Des outils simples, pensés pour organiser une semaine chargée.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-calendar-days fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Un mois lisible</h3>
                    <p class="feature-description">Vos événements apparaissent directement dans le calendrier, avec le
                        détail du jour et la liste des prochains rendez-vous.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-bell fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Des rappels sur mesure</h3>
                    <p class="feature-description">Jusqu'à cinq rappels par événement, de l'heure exacte à une semaine
                        avant, par e-mail et dans l'application.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-rotate fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Événements récurrents</h3>
                    <p class="feature-description">Cours, réunions, anniversaires : répétez un événement chaque jour,
                        semaine, mois ou année, avec une date de fin si besoin.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-magnifying-glass fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Recherche et catégories</h3>
                    <p class="feature-description">Retrouvez un événement en quelques lettres et filtrez votre agenda
                        par catégorie : travail, études, santé…</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-circle-half-stroke fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Thème clair ou sombre</h3>
                    <p class="feature-description">L'interface suit automatiquement le thème de votre appareil, et vous
                        pouvez le changer à tout moment.</p>
                </div>
                <div class="feature-card animate-slide-up">
                    <div class="feature-icon"><i class="fas fa-universal-access fa-2x" aria-hidden="true"></i></div>
                    <h3 class="feature-title">Mobile et clavier</h3>
                    <p class="feature-description">Conçu pour les petits écrans comme pour l'ordinateur, et utilisable
                        entièrement au clavier.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how" class="section about-section">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title animate-slide-up">Comment ça marche</h2>
                <p class="section-subtitle animate-slide-up">Trois étapes, sans installation.</p>
            </div>
            <ol class="grid md:grid-cols-3 gap-8 steps-list">
                <li class="feature-card animate-slide-up">
                    <h3 class="feature-title">1. Créez votre compte</h3>
                    <p class="feature-description">Une adresse e-mail et un mot de passe suffisent. Un lien de
                        confirmation vous est envoyé.</p>
                </li>
                <li class="feature-card animate-slide-up">
                    <h3 class="feature-title">2. Ajoutez vos événements</h3>
                    <p class="feature-description">Un titre, une date, une heure : c'est tout. Lieu, description et
                        répétition restent facultatifs.</p>
                </li>
                <li class="feature-card animate-slide-up">
                    <h3 class="feature-title">3. Soyez prévenu à temps</h3>
                    <p class="feature-description">Choisissez quand être rappelé ; la notification arrive dans
                        l'application et, si vous le souhaitez, par e-mail.</p>
                </li>
            </ol>
        </div>
    </section>

    <section id="about" class="section cta-section">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="section-title animate-slide-up">À propos d'AgendDAY</h2>
                <p class="section-subtitle animate-slide-up">AgendDAY est un projet personnel développé par Cyprien
                    Castells. Gratuit, sans publicité ni traceur publicitaire, il est hébergé en France chez OVHcloud.</p>
                <?php if (!($isLoggedIn ?? false)): ?>
                    <a href="/inscription" class="btn btn-primary btn-large mt-8 animate-slide-up">Commencer maintenant</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
