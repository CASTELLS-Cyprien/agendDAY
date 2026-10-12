-- Migration : événements enrichis, rappels configurables, notifications, préférences.
--
-- Migration ADDITIVE : aucune colonne ni donnée existante n'est supprimée ou modifiée
-- (la colonne historique events.sentReminder est conservée).
--
-- ============================================================================
-- AVANT D'EXÉCUTER (phpMyAdmin OVH > onglet SQL) :
--   1. Faire une sauvegarde de la base (OVH > Bases de données > Sauvegardes).
--   2. Vérifier la structure existante :
--        SHOW CREATE TABLE users;
--        SHOW CREATE TABLE events;
--      Les clés étrangères ci-dessous supposent :
--        - moteur InnoDB pour `users` et `events` ;
--        - users.id et events.id de type INT (signé, comme le crée phpMyAdmin par défaut).
--      Si l'un des id est INT UNSIGNED, remplacer « INT » par « INT UNSIGNED » pour
--      event_id et user_id dans les deux CREATE TABLE ci-dessous.
--      Si une table est en MyISAM : ALTER TABLE events ENGINE=InnoDB; (idem users).
--   3. Le code applicatif doit être déployé APRÈS cette migration : l'ancien code
--      continue de fonctionner avec le nouveau schéma, l'inverse n'est pas vrai.
-- ============================================================================
--
-- Ordre des instructions : les CREATE TABLE (seules instructions susceptibles
-- d'échouer à cause des types) sont exécutés en premier ; en cas d'échec, rien
-- d'autre n'a été modifié et la migration peut être relancée après correction.

SET NAMES utf8mb4;

-- 1) Rappels programmés ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_reminders (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       INT NOT NULL,
    offset_minutes INT UNSIGNED NOT NULL,
    occurrence_at  DATETIME NULL,
    remind_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_reminders_event_offset (event_id, offset_minutes),
    KEY idx_event_reminders_remind_at (remind_at),
    CONSTRAINT fk_event_reminders_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Notifications in-app + journal des e-mails ---------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id               INT NOT NULL,
    event_id              INT NULL,
    type                  VARCHAR(20) NOT NULL DEFAULT 'reminder',
    offset_minutes        INT UNSIGNED NULL,
    occurrence_at         DATETIME NULL,
    all_day               TINYINT(1) NOT NULL DEFAULT 0,
    title                 VARCHAR(255) NOT NULL,
    email_status          VARCHAR(10) NOT NULL DEFAULT 'none',
    email_attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    email_next_attempt_at DATETIME NULL,
    email_locked_at       DATETIME NULL,
    email_sent_at         DATETIME NULL,
    email_error           VARCHAR(255) NULL,
    read_at               DATETIME NULL,
    created_at            DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_occurrence (event_id, offset_minutes, occurrence_at),
    KEY idx_notifications_user (user_id, created_at),
    KEY idx_notifications_email (email_status, email_next_attempt_at),
    CONSTRAINT fk_notifications_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_notifications_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Nouveaux champs d'événement -------------------------------------------------
--    `time` reste l'heure de début (00:00 pour une journée entière).
ALTER TABLE events
    ADD COLUMN end_time         TIME NULL AFTER time,
    ADD COLUMN all_day          TINYINT(1) NOT NULL DEFAULT 0 AFTER end_time,
    ADD COLUMN location         VARCHAR(200) NULL,
    ADD COLUMN category         VARCHAR(20) NOT NULL DEFAULT 'general',
    ADD COLUMN recurrence       VARCHAR(10) NOT NULL DEFAULT 'none',
    ADD COLUMN recurrence_until DATE NULL,
    ADD COLUMN created_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD INDEX idx_events_user_date (userID, dateEvent);

-- 4) Préférences de notification -----------------------------------------------
ALTER TABLE users
    ADD COLUMN email_reminders          TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN default_reminder_minutes INT NULL DEFAULT 15;

-- 5) Reprise des rappels existants ------------------------------------------------
--    L'ancien système envoyait un e-mail dans les 5 minutes précédant l'événement :
--    chaque événement futur non encore rappelé reçoit un rappel « 5 minutes avant ».
INSERT IGNORE INTO event_reminders (event_id, offset_minutes, occurrence_at, remind_at)
SELECT id,
       5,
       TIMESTAMP(dateEvent, time),
       TIMESTAMP(dateEvent, time) - INTERVAL 5 MINUTE
FROM events
WHERE (sentReminder IS NULL OR sentReminder = 0)
  AND TIMESTAMP(dateEvent, time) > NOW();

-- Vérifications après exécution :
--   SELECT COUNT(*) FROM event_reminders;
--   SHOW CREATE TABLE events;
