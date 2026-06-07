<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($metaTitle ?? 'AgendDAY') ?></title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/png" href="/assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= $this->asset('/assets/css/global.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('/assets/css/forms.css') ?>">

    <?php if ($withRecaptcha ?? false): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>

<body>
    <?= $content ?>
    <script src="<?= $this->asset('/assets/js/theme.js') ?>"></script>
</body>

</html>
