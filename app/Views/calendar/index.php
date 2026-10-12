<?php
/** @var array $calendarConfig */
$categories = $calendarConfig['categories'];
?>
<script type="application/json" id="calendarConfig"><?= json_encode($calendarConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="calendar-page" id="calendarApp">
    <section class="cal-main" aria-labelledby="calendarTitle">
        <div class="cal-toolbar">
            <div class="cal-toolbar__nav">
                <button type="button" class="btn btn-secondary" id="todayBtn">Aujourd'hui</button>
                <div class="cal-toolbar__arrows">
                    <button type="button" class="btn btn-ghost btn-icon" id="prevBtn" aria-label="Mois précédent">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-ghost btn-icon" id="nextBtn" aria-label="Mois suivant">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
                <h1 class="cal-title" id="calendarTitle" aria-live="polite">Calendrier</h1>
            </div>

            <div class="cal-toolbar__tools">
                <div class="search" role="search">
                    <label for="searchInput" class="sr-only">Rechercher un événement</label>
                    <i class="fas fa-magnifying-glass search__icon" aria-hidden="true"></i>
                    <input type="search" id="searchInput" class="input search__input" placeholder="Rechercher…"
                        autocomplete="off" maxlength="100" aria-controls="searchResults" aria-expanded="false">
                    <div class="popover search__results" id="searchResults" hidden>
                        <p class="search__status" id="searchStatus" aria-live="polite"></p>
                        <ul class="event-list event-list--compact" id="searchList"></ul>
                    </div>
                </div>

                <div class="segmented" role="group" aria-label="Mode d'affichage">
                    <button type="button" class="segmented__btn" data-view="month" aria-pressed="true">
                        <i class="fas fa-calendar-days" aria-hidden="true"></i> Mois
                    </button>
                    <button type="button" class="segmented__btn" data-view="list" aria-pressed="false">
                        <i class="fas fa-list" aria-hidden="true"></i> Liste
                    </button>
                </div>

                <button type="button" class="btn btn-primary cal-new-btn" id="newEventBtn">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nouvel événement
                </button>
            </div>
        </div>

        <div class="cal-subbar">
            <div class="filters" role="group" aria-label="Catégories affichées">
                <?php foreach ($categories as $key => $label): ?>
                    <button type="button" class="filter-chip" data-category="<?= htmlspecialchars($key) ?>" aria-pressed="true">
                        <span class="cat-dot cat--<?= htmlspecialchars($key) ?>" aria-hidden="true"></span>
                        <?= htmlspecialchars($label) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="month-jump">
                <label for="monthPicker" class="month-jump__label">Aller au mois</label>
                <input type="month" id="monthPicker" class="input month-jump__input" min="1970-01" max="2100-12"
                    placeholder="AAAA-MM" pattern="\d{4}-\d{2}">
            </div>
        </div>

        <p class="alert alert--error cal-error" id="calError" role="alert" hidden></p>

        <div class="month" id="monthView">
            <div class="month__grid" id="monthGrid" role="grid" aria-labelledby="calendarTitle" aria-describedby="gridHelp"></div>
            <p class="sr-only" id="gridHelp">
                Flèches : changer de jour. Début et Fin : début et fin de semaine.
                Page précédente et Page suivante : changer de mois. Entrée : afficher les événements du jour.
            </p>
        </div>

        <div class="agenda" id="listView" hidden></div>
    </section>

    <aside class="cal-side" aria-label="Événements">
        <section class="panel day-panel" aria-labelledby="dayTitle">
            <header class="panel__header">
                <div>
                    <p class="panel__eyebrow" id="dayEyebrow"></p>
                    <h2 class="panel__title" id="dayTitle" tabindex="-1"></h2>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="addOnDayBtn">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter
                </button>
            </header>
            <ul class="event-list" id="dayEvents" aria-labelledby="dayTitle"></ul>
            <div class="empty-state" id="dayEmpty" hidden>
                <i class="fas fa-mug-hot" aria-hidden="true"></i>
                <p>Aucun événement ce jour-là.</p>
            </div>
        </section>

        <section class="panel upcoming-panel" aria-labelledby="upcomingTitle">
            <header class="panel__header">
                <h2 class="panel__title" id="upcomingTitle">À venir</h2>
                <span class="panel__hint">14 prochains jours</span>
            </header>
            <ul class="event-list event-list--compact" id="upcomingList"></ul>
            <p class="empty-state empty-state--inline" id="upcomingEmpty" hidden>Rien de prévu pour les 14 prochains jours.</p>
        </section>
    </aside>
</div>

<button type="button" class="fab" id="fabNewEvent" aria-label="Nouvel événement">
    <i class="fas fa-plus" aria-hidden="true"></i>
</button>

<!-- Détails d'un événement -->
<dialog class="dialog dialog--details" id="detailsDialog" aria-labelledby="detailsTitle">
    <div class="dialog__header details-header" id="detailsHeader">
        <div class="details-header__text">
            <span class="badge details-header__category" id="detailsCategory"></span>
            <h2 class="dialog__title" id="detailsTitle"></h2>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Fermer">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="dialog__body">
        <dl class="details-list">
            <div class="details-list__row">
                <dt><i class="fas fa-clock" aria-hidden="true"></i><span class="sr-only">Date et heure</span></dt>
                <dd id="detailsWhen"></dd>
            </div>
            <div class="details-list__row" id="detailsLocationRow">
                <dt><i class="fas fa-location-dot" aria-hidden="true"></i><span class="sr-only">Lieu</span></dt>
                <dd id="detailsLocation"></dd>
            </div>
            <div class="details-list__row" id="detailsRecurrenceRow">
                <dt><i class="fas fa-rotate" aria-hidden="true"></i><span class="sr-only">Répétition</span></dt>
                <dd id="detailsRecurrence"></dd>
            </div>
            <div class="details-list__row">
                <dt><i class="fas fa-bell" aria-hidden="true"></i><span class="sr-only">Rappels</span></dt>
                <dd id="detailsReminders"></dd>
            </div>
            <div class="details-list__row" id="detailsDescriptionRow">
                <dt><i class="fas fa-align-left" aria-hidden="true"></i><span class="sr-only">Description</span></dt>
                <dd class="details-list__description" id="detailsDescription"></dd>
            </div>
        </dl>
    </div>
    <div class="dialog__footer">
        <button type="button" class="btn btn-danger-ghost" id="detailsDelete">
            <i class="fas fa-trash" aria-hidden="true"></i> Supprimer
        </button>
        <span class="dialog__spacer"></span>
        <button type="button" class="btn btn-secondary" id="detailsDuplicate">
            <i class="fas fa-copy" aria-hidden="true"></i> Dupliquer
        </button>
        <button type="button" class="btn btn-primary" id="detailsEdit">
            <i class="fas fa-pen" aria-hidden="true"></i> Modifier
        </button>
    </div>
</dialog>

<!-- Création / modification -->
<dialog class="dialog dialog--form" id="eventDialog" aria-labelledby="eventDialogTitle">
    <form id="eventForm" class="dialog__form" novalidate>
        <div class="dialog__header">
            <h2 class="dialog__title" id="eventDialogTitle">Nouvel événement</h2>
            <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Fermer">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="dialog__body">
            <div class="alert alert--error" id="eventFormError" role="alert" hidden></div>
            <p class="alert alert--info" id="seriesNote" hidden>
                <i class="fas fa-rotate" aria-hidden="true"></i>
                Événement récurrent : les modifications s'appliquent à toutes les occurrences.
            </p>

            <input type="hidden" name="eventId" id="eventId">

            <div class="field">
                <label class="field-label" for="eventTitle">Titre</label>
                <input type="text" class="input" id="eventTitle" name="title" maxlength="200" autocomplete="off"
                    required aria-describedby="error-title">
                <p class="field-error" id="error-title" data-error-for="title"></p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field-label" for="eventDate" id="eventDateLabel">Date</label>
                    <input type="date" class="input" id="eventDate" name="date" required min="1970-01-01" max="2100-12-31"
                        aria-describedby="error-date">
                    <p class="field-error" id="error-date" data-error-for="date"></p>
                </div>
                <div class="field field--inline">
                    <label class="checkbox">
                        <input type="checkbox" id="eventAllDay" name="allDay" value="1">
                        Journée entière
                    </label>
                </div>
            </div>

            <div class="field-row" id="timeFields">
                <div class="field">
                    <label class="field-label" for="eventStart">Début</label>
                    <input type="time" class="input" id="eventStart" name="time" required aria-describedby="error-time">
                    <p class="field-error" id="error-time" data-error-for="time"></p>
                </div>
                <div class="field">
                    <label class="field-label" for="eventEnd">Fin <span class="optional">(facultatif)</span></label>
                    <input type="time" class="input" id="eventEnd" name="endTime" aria-describedby="error-endTime">
                    <p class="field-error" id="error-endTime" data-error-for="endTime"></p>
                </div>
            </div>

            <fieldset class="field" aria-describedby="error-category">
                <legend>Catégorie</legend>
                <div class="category-picker">
                    <?php foreach ($categories as $key => $label): ?>
                        <label class="category-option">
                            <input type="radio" name="category" value="<?= htmlspecialchars($key) ?>">
                            <span class="category-option__box">
                                <span class="cat-dot cat--<?= htmlspecialchars($key) ?>" aria-hidden="true"></span>
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-error" id="error-category" data-error-for="category"></p>
            </fieldset>

            <fieldset class="field" aria-describedby="reminderHint error-reminders">
                <legend>Rappels</legend>
                <div class="reminder-grid">
                    <?php foreach ($calendarConfig['reminderOffsets'] as $offset): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="reminders[]" value="<?= (int) $offset['value'] ?>">
                            <?= htmlspecialchars($offset['label']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-hint" id="reminderHint"></p>
                <p class="field-error" id="error-reminders" data-error-for="reminders"></p>
            </fieldset>

            <details class="more-options" id="moreOptions">
                <summary>Lieu, description et répétition</summary>

                <div class="field">
                    <label class="field-label" for="eventLocation">Lieu <span class="optional">(facultatif)</span></label>
                    <input type="text" class="input" id="eventLocation" name="location" maxlength="200" autocomplete="off"
                        aria-describedby="error-location">
                    <p class="field-error" id="error-location" data-error-for="location"></p>
                </div>

                <div class="field">
                    <label class="field-label" for="eventDescription">Description <span class="optional">(facultatif)</span></label>
                    <textarea class="textarea" id="eventDescription" name="descriptionEvent" maxlength="2000" rows="3"
                        aria-describedby="descriptionCounter error-description"></textarea>
                    <p class="field-hint" id="descriptionCounter"></p>
                    <p class="field-error" id="error-description" data-error-for="description"></p>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="eventRecurrence">Répétition</label>
                        <select class="select" id="eventRecurrence" name="recurrence" aria-describedby="error-recurrence"></select>
                        <p class="field-error" id="error-recurrence" data-error-for="recurrence"></p>
                    </div>
                    <div class="field" id="untilField" hidden>
                        <label class="field-label" for="eventUntil">Jusqu'au <span class="optional">(facultatif)</span></label>
                        <input type="date" class="input" id="eventUntil" name="recurrenceUntil" min="1970-01-01" max="2100-12-31"
                            aria-describedby="error-recurrenceUntil">
                        <p class="field-error" id="error-recurrenceUntil" data-error-for="recurrenceUntil"></p>
                    </div>
                </div>
            </details>
        </div>

        <div class="dialog__footer">
            <button type="button" class="btn btn-secondary" data-close>Annuler</button>
            <button type="submit" class="btn btn-primary" id="eventSubmit">Enregistrer</button>
        </div>
    </form>
</dialog>
