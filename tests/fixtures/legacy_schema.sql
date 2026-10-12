-- Schéma historique d'agendDAY reconstitué à partir du code (avant la migration
-- 2026_10_12) : sert à vérifier que la migration s'applique sur une base existante.
-- Types supposés : ceux créés par défaut par phpMyAdmin (INT signé, InnoDB).

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
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
    id               INT NOT NULL AUTO_INCREMENT,
    title            VARCHAR(255) NOT NULL,
    time             TIME NOT NULL,
    descriptionEvent TEXT NULL,
    userID           INT NOT NULL,
    dateEvent        DATE NOT NULL,
    sentReminder     TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY userID (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, nomUtilisateur, email, motDePasse, is_confirmed)
VALUES (1, 'Ancien', 'ancien@agendday.test', '$2y$10$abcdefghijklmnopqrstuuJ0Ym1q8b4cY3H0x1Ue2u3o4l5s6t7u8', 1);

-- Événement futur non rappelé, futur déjà rappelé, et passé
INSERT INTO events (title, time, descriptionEvent, userID, dateEvent, sentReminder) VALUES
    ('Futur à rappeler', '10:00:00', 'desc', 1, CURDATE() + INTERVAL 3 DAY, 0),
    ('Futur déjà rappelé', '11:00:00', NULL, 1, CURDATE() + INTERVAL 3 DAY, 1),
    ('Passé', '09:00:00', NULL, 1, CURDATE() - INTERVAL 3 DAY, 0);
