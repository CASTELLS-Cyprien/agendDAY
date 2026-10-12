<?php
/**
 * @var array $user
 * @var array<int, string> $reminderOffsets
 */
$defaultReminder = $user['default_reminder_minutes'];
?>
<div class="page">
    <header class="page-header">
        <a href="/calendrier" class="back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour au calendrier</a>
        <h1>Préférences</h1>
    </header>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <section class="panel settings-section" id="notifications" aria-labelledby="notificationsHeading">
        <h2 id="notificationsHeading">Rappels et notifications</h2>
        <p>
            Chaque rappel crée une notification dans l'application (icône <i class="fas fa-bell" aria-hidden="true"></i><span class="sr-only">cloche</span>
            en haut de l'écran) et, si vous le souhaitez, un e-mail.
        </p>

        <form method="post" action="/parametres" data-busy-label="Enregistrement…">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">

            <label class="checkbox">
                <input type="checkbox" name="email_reminders" value="1" <?= (int) $user['email_reminders'] === 1 ? 'checked' : '' ?>>
                <span class="checkbox__text">
                    <strong>Recevoir les rappels par e-mail</strong>
                    <small>Envoyés à <?= htmlspecialchars($user['email']) ?>. Les notifications dans l'application restent toujours actives.</small>
                </span>
            </label>

            <div class="field">
                <label class="field-label" for="defaultReminder">Rappel proposé pour les nouveaux événements</label>
                <select class="select" id="defaultReminder" name="default_reminder" aria-describedby="defaultReminderHint">
                    <option value="" <?= $defaultReminder === null ? 'selected' : '' ?>>Aucun rappel</option>
                    <?php foreach ($reminderOffsets as $minutes => $label): ?>
                        <option value="<?= (int) $minutes ?>" <?= $defaultReminder !== null && (int) $defaultReminder === (int) $minutes ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint" id="defaultReminderHint">Pré-coché à la création ; modifiable pour chaque événement.</p>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer les préférences</button>
        </form>
    </section>

    <section class="panel settings-section" aria-labelledby="howHeading">
        <h2 id="howHeading">Fonctionnement des rappels</h2>
        <ul class="how-list">
            <li>Les rappels sont traités automatiquement toutes les 5 minutes environ : un rappel peut donc arriver avec quelques minutes de décalage.</li>
            <li>Pour un événement sur la journée entière, les délais sont calculés à partir de 9 h (« 1 jour avant » = la veille à 9 h).</li>
            <li>Un rappel en retard est tout de même envoyé tant que l'événement n'a pas commencé depuis plus de 15 minutes ; au-delà, il est abandonné.</li>
            <li>Si l'événement est modifié ou supprimé, ses rappels sont recalculés ou annulés automatiquement.</li>
            <li>« E-mail transmis au serveur d'envoi » signifie que notre serveur de messagerie l'a accepté. La réception dépend ensuite de votre messagerie : pensez à vérifier vos courriers indésirables.</li>
        </ul>
    </section>

    <section class="panel settings-section" aria-labelledby="accountHeading">
        <h2 id="accountHeading">Compte</h2>
        <dl class="info-list">
            <dt>Nom d'utilisateur</dt>
            <dd><?= htmlspecialchars($user['nomUtilisateur']) ?></dd>
            <dt>Adresse e-mail</dt>
            <dd><?= htmlspecialchars($user['email']) ?></dd>
        </dl>
    </section>

    <section class="panel settings-section settings-section--danger" id="suppression" aria-labelledby="deleteHeading">
        <h2 id="deleteHeading">Supprimer mon compte</h2>
        <p>
            Supprime définitivement votre compte, vos événements, vos rappels et vos notifications.
            Cette action est irréversible.
        </p>

        <form method="post" action="/parametres/supprimer-compte" data-busy-label="Suppression…">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrfToken()) ?>">

            <div class="field">
                <label class="field-label" for="deletePassword">Mot de passe actuel</label>
                <input type="password" class="input" id="deletePassword" name="password" required autocomplete="current-password">
            </div>

            <label class="checkbox">
                <input type="checkbox" name="confirm" value="1" required>
                <span>Je comprends que mon compte et toutes mes données seront définitivement supprimés.</span>
            </label>

            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash" aria-hidden="true"></i> Supprimer définitivement mon compte
            </button>
        </form>
    </section>
</div>
