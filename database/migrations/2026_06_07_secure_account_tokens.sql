-- Migration : sécurisation des jetons de confirmation de compte / réinitialisation
-- de mot de passe (table `users`).
--
-- À exécuter manuellement sur la base de production (phpMyAdmin / CLI MySQL) —
-- ce projet ne dispose pas d'outil de migration automatisé.
--
-- Contexte :
--   1. Le jeton de confirmation de compte n'avait aucune date d'expiration en
--      base, alors que l'email envoyé annonce « Ce lien expire dans 24 heures ».
--   2. Les jetons (confirmation et réinitialisation) étaient stockés en clair :
--      en cas de fuite de la base, ils seraient directement exploitables.
--      Le code stocke désormais un hash SHA-256 du jeton (le jeton brut continue
--      de transiter uniquement par email, comme avant).

-- 1) Ajout de la colonne d'expiration du jeton de confirmation
ALTER TABLE users
    ADD COLUMN confirmation_token_expiration DATETIME NULL AFTER confirmation_token;

-- 2) Délai de grâce de 24h pour les confirmations déjà en attente, afin de ne
--    pas invalider immédiatement les emails de confirmation déjà envoyés.
UPDATE users
SET confirmation_token_expiration = NOW() + INTERVAL 24 HOUR
WHERE is_confirmed = 0 AND confirmation_token IS NOT NULL;

-- 3) Conversion des jetons existants (clair -> hash SHA-256) pour que les liens
--    déjà envoyés par email restent valides après la mise en production du
--    correctif (le code compare désormais hash(jeton_reçu) au hash stocké).
UPDATE users SET confirmation_token = SHA2(confirmation_token, 256) WHERE confirmation_token IS NOT NULL;
UPDATE users SET reset_token        = SHA2(reset_token, 256)        WHERE reset_token IS NOT NULL;
