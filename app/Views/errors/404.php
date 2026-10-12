<main class="form-container">
    <div class="form-box text-center">
        <div class="form-header">
            <a href="/"><img src="/assets/images/logo.png" alt="Accueil AgendDAY" class="form-logo" width="80" height="80"></a>
            <h1 class="form-title">Page introuvable</h1>
            <p class="form-subtitle">L'adresse demandée n'existe pas ou a été déplacée.</p>
        </div>
        <div class="form-actions">
            <?php if ($isLoggedIn ?? false): ?>
                <a href="/calendrier" class="btn btn-primary btn-block">Retour au calendrier</a>
            <?php else: ?>
                <a href="/" class="btn btn-primary btn-block">Retour à l'accueil</a>
            <?php endif; ?>
        </div>
    </div>
</main>
