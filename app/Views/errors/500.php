<?php
// Page autonome : affichée par Core\ErrorHandler hors de tout contrôleur.
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Erreur — AgendDAY</title>
    <script src="/assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>

<body>
    <main class="form-container">
        <div class="form-box text-center">
            <div class="form-header">
                <img src="/assets/images/logo.png" alt="" class="form-logo" width="80" height="80">
                <h1 class="form-title">Une erreur est survenue</h1>
                <p class="form-subtitle">
                    Le service est momentanément indisponible. Veuillez réessayer dans quelques instants.
                </p>
            </div>
            <div class="form-actions">
                <a href="/" class="btn btn-primary btn-block">Retour à l'accueil</a>
            </div>
        </div>
    </main>
</body>

</html>
