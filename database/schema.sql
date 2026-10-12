-- Schéma complet d'agendDAY — installation neuve (MySQL 5.7+ / 8.0, MariaDB 10.3+).
--
-- Pour une base existante (production), NE PAS exécuter ce fichier : appliquer les
-- migrations de database/migrations/ dans l'ordre chronologique.
--
-- Les tables `users` et `events` conservent les noms de colonnes historiques
-- (nomUtilisateur, motDePasse, dateEvent, descriptionEvent, userID…) pour rester
-- compatibles avec la base déjà en production.

SET NAMES utf8mb4;

CREATE TABLE users (
    id                            INT NOT NULL AUTO_INCREMENT,
    nomUtilisateur                VARCHAR(255) NOT NULL,
    email                         VARCHAR(255) NOT NULL,
    motDePasse                    VARCHAR(255) NOT NULL,
    confirmation_token            VARCHAR(255) NULL,
    confirmation_token_expiration DATETIME NULL,
    is_confirmed                  TINYINT(1) NOT NULL DEFAULT 0,
    reset_token                   VARCHAR(255) NULL,
    reset_token_expiration        DATETIME NULL,
    email_reminders               TINYINT(1) NOT NULL DEFAULT 1,
    default_reminder_minutes      INT NULL DEFAULT 15,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id               INT NOT NULL AUTO_INCREMENT,
    title            VARCHAR(255) NOT NULL,
    time             TIME NOT NULL,                 -- heure de début (00:00 si journée entière)
    end_time         TIME NULL,                     -- heure de fin, facultative
    all_day          TINYINT(1) NOT NULL DEFAULT 0,
    descriptionEvent TEXT NULL,
    location         VARCHAR(200) NULL,
    category         VARCHAR(20) NOT NULL DEFAULT 'general',
    recurrence       VARCHAR(10) NOT NULL DEFAULT 'none', -- none|daily|weekly|monthly|yearly
    recurrence_until DATE NULL,
    userID           INT NOT NULL,
    dateEvent        DATE NOT NULL,                 -- date (de début de série si récurrent)
    sentReminder     TINYINT(1) NOT NULL DEFAULT 0, -- historique, plus utilisé depuis event_reminders
    created_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_user_date (userID, dateEvent),
    CONSTRAINT fk_events_user FOREIGN KEY (userID) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un rappel = un délai (en minutes) avant chaque occurrence d'un événement.
-- remind_at / occurrence_at désignent la prochaine occurrence à rappeler ;
-- NULL quand il n'y a plus rien à rappeler (événement passé, série terminée).
CREATE TABLE event_reminders (
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

-- Notifications in-app ET journal d'envoi des e-mails de rappel.
-- La clé unique (event_id, offset_minutes, occurrence_at) garantit qu'une même
-- occurrence ne déclenche qu'une seule notification, même si deux exécutions du
-- cron se chevauchent.
-- email_status : none | disabled | pending | sending | sent | failed | cancelled | skipped
--   « sent » signifie « accepté par le serveur SMTP », pas « délivré ».
CREATE TABLE notifications (
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
