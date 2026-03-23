<?php
// ============================================================
// reset_password.php — Réinitialisation du mot de passe
// Emplacement : treatment/reset_password.php
// ============================================================
session_start();

require_once __DIR__ . '/connexion_db.php';

// --- Vérification du token ---
if (!isset($_GET['token'])) {
    $_SESSION['message'] = "Aucun token fourni.";
    header("Location: /mot-de-passe-oublie");
    exit();
}

$token = $_GET['token'];
$bdd = null;
$user = null;

try {
    $bdd = getDatabaseConnection();
    $stmt = $bdd->prepare(
        "SELECT id, nomUtilisateur, motDePasse
         FROM users
         WHERE reset_token = :token
           AND reset_token_expiration > NOW()"
    );
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur de connexion.";
    header("Location: /mot-de-passe-oublie");
    exit();
}

if (!$user) {
    $_SESSION['message'] = "Lien de réinitialisation invalide ou expiré.";
    header("Location: /mot-de-passe-oublie");
    exit();
}

// --- Traitement du formulaire ---
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetPassword'])) {
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmNewPassword'] ?? '';

    if (strlen($newPassword) < 8) {
        $formError = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($newPassword !== $confirmPassword) {
        $formError = "Les mots de passe ne correspondent pas.";
    } elseif (password_verify($newPassword, $user['motDePasse'])) {
        $formError = "Le nouveau mot de passe ne peut pas être identique à l'ancien.";
    } else {
        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $bdd->prepare(
                "UPDATE users
                 SET motDePasse = :pwd,
                     reset_token = NULL,
                     reset_token_expiration = NULL
                 WHERE id = :id"
            );
            $upd->execute([':pwd' => $hash, ':id' => $user['id']]);

            $_SESSION['message'] = "Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.";
            header("Location: /connexion");
            exit();
        } catch (PDOException $e) {
            $formError = "Erreur lors de la mise à jour. Veuillez réessayer.";
            error_log("Erreur reset_password: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgendDAY — Réinitialiser le mot de passe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
    <link rel="icon" href="/assets/images/logo.png">
</head>

<body>
    <div class="form-container">
        <div class="form-box">
            <?php if ($formError): ?>
                <p class="form-error"><?= htmlspecialchars($formError) ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['message'])): ?>
                <p class="form-error"><?= htmlspecialchars($_SESSION['message']) ?></p>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="form-header">
                <a href="/">
                    <img src="/assets/images/logo.png" alt="AgendDAY" class="form-logo"
                        style="width:80px;margin-bottom:var(--spacing-4);">
                </a>
                <h1 class="form-title">Nouveau mot de passe</h1>
                <p class="form-subtitle">Choisissez un mot de passe sécurisé (8 caractères minimum)</p>
            </div>

            <form action="" method="post">
                <div class="form-group">
                    <label class="form-label" for="newPassword">Nouveau mot de passe</label>
                    <input type="password" class="form-input" id="newPassword" name="newPassword" required
                        minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmNewPassword">Confirmer le mot de passe</label>
                    <input type="password" class="form-input" id="confirmNewPassword" name="confirmNewPassword" required
                        minlength="8">
                </div>
                <div class="form-actions">
                    <button type="submit" class="form-submit" name="resetPassword">Réinitialiser</button>
                </div>
                <div class="form-footer">
                    <p><a href="/connexion" class="form-link">Retour à la connexion</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="/assets/js/theme.js"></script>
</body>

</html>