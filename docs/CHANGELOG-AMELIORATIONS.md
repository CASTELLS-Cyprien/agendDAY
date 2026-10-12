# Bilan des améliorations — octobre 2026

Ce document récapitule les modifications, la procédure de mise en production et les
limites restantes. Le diagnostic initial figure dans [AUDIT.md](AUDIT.md).

## 1. Modifications par priorité

### P0 — Sécurité, perte de données, fiabilité

| Problème | Correction | Vérification |
|---|---|---|
| XSS via guillemets dans la description (`data-full-text`) | Tout le rendu dynamique passe par `textContent` / `createElement` (`AgendDAY.el`) ; plus aucun `innerHTML` avec des données utilisateur | Test HTTP (titre `<img onerror>`) + contrôle dans le navigateur |
| Purge quotidienne de tout l'historique | Conservation configurable (`EVENT_RETENTION_DAYS`, 365 j par défaut ; séries sans fin jamais purgées) | Test d'intégration « purge de conservation » |
| Fuseaux horaires incohérents | `bootstrap.php` fixe `APP_TIMEZONE` pour PHP et aligne la session MySQL (`SET time_zone`) | Tests de planification |
| Rappels perdus, doublons possibles, mode test qui envoie réellement | Nouveau moteur idempotent (cf. §2), verrou `GET_LOCK`, simulation `--dry-run` sans écriture | 12 tests d'intégration + test HTTP du cron |
| Secret du cron dans l'URL | En-tête `X-Cron-Secret` (paramètre `?secret=` toléré pour compatibilité) ; CLI sans secret | Test HTTP (403 sans / avec mauvais secret) |
| Erreurs non gérées | `Core\ErrorHandler` : journalisation, page 500 générique, JSON pour l'API, `display_errors` coupé en production | — |
| Scripts inline (CSP `unsafe-inline`) | Scripts déplacés dans `assets/js` ; CSP `script-src` sans `unsafe-inline` | Grep : aucun `<script>` inline exécutable |
| Déconnexion par lien GET, fixation de session | Déconnexion en POST + CSRF, `session.use_strict_mode`, jeton CSRF renouvelé à la connexion, cookie supprimé | Test HTTP « déconnexion » |
| Fichiers internes exposés | `.htaccess` : blocage de `database/`, `storage/`, `tests/`, `dev/`, `docs/`, fichiers cachés, `composer.*` | Test HTTP (403) via le routeur de développement |

### P1 — Fonctionnalités essentielles, rappels, ergonomie

- **Événements** : date modifiable (impossible auparavant), heure de fin, journée entière,
  lieu, catégorie, récurrence avec date de fin. Validation serveur par champ
  (`EventInput`) et côté client, messages explicites, saisie conservée en cas d'erreur.
- **Rappels** : plusieurs délais par événement, rappel par défaut, e-mails activables.
- **Centre de notifications** alimenté uniquement par le moteur de rappels.
- **Calendrier** : événements visibles dans les cases (« +N » pour une journée chargée),
  aujourd'hui / sélection / jours occupés distingués par la forme et pas seulement par
  la couleur, panneau du jour, « À venir », vue liste, recherche, filtres.
- **Dialogues** natifs (`<dialog>`) : détails avec actions rapides (modifier, dupliquer,
  supprimer), confirmation avant suppression ou abandon d'une saisie, protection contre
  la double soumission.
- **Accessibilité** : grille ARIA à focus itinérant (le focus était perdu à chaque
  flèche), raccourcis (flèches, Début/Fin, Page préc./suiv., Entrée, « n », « / »),
  lien d'évitement, annonces `aria-live`, libellés explicites.
- **Comptes** : retour à la page demandée après connexion (liens des e-mails), messages
  de succès qui ne s'affichent plus en rouge, suppression du compte en libre-service.

### P2 — Design, contenu, maintenabilité

- Design system (`global.css`) : jetons de couleur contrastés AA dans les deux thèmes,
  échelle d'espacement, rayons, composants (boutons, champs, alertes, badges, toasts,
  dialogues). Le thème suit la préférence système par défaut.
- Responsive vérifié de 320 px à 1440 px ; sur mobile, les dialogues s'ouvrent depuis le
  bas de l'écran et un bouton flottant permet de créer un événement.
- Page d'accueil : témoignages fictifs et affirmations non fondées remplacés par la
  description des fonctionnalités réelles. Politique de confidentialité mise à jour
  (données réellement collectées, prestataires, durées de conservation).
- Code : `bootstrap.php` commun (fin des autoloaders dupliqués), logique métier en
  classes pures testables, transactions pour les écritures multiples.

## 2. Base de données

Fichier : `database/migrations/2026_10_12_events_reminders_notifications.sql`.

- Migration **additive** : nouvelles tables `event_reminders` et `notifications`, colonnes
  ajoutées à `events` (heure de fin, journée entière, lieu, catégorie, récurrence,
  horodatages) et à `users` (préférences). Aucune colonne supprimée ni modifiée.
- La colonne historique `events.sentReminder` est conservée : l'ancien code reste
  compatible avec le nouveau schéma, ce qui permet un retour arrière du code sans
  toucher à la base.
- Chaque événement futur non encore rappelé reçoit un rappel « 5 minutes avant »,
  l'équivalent de l'ancien comportement.
- Testée automatiquement sur un schéma historique reconstitué à partir du code
  (`tests/fixtures/legacy_schema.sql`). **La structure réelle de production n'a pas pu être
  consultée** : suivez les vérifications préalables indiquées en tête du fichier.

## 3. Procédure de mise en production (OVH)

1. **Sauvegarde** : Espace client OVH › Bases de données › Sauvegardes (ou export phpMyAdmin).
2. **Vérification** : dans phpMyAdmin, exécuter `SHOW CREATE TABLE users;` et
   `SHOW CREATE TABLE events;`. Il faut des tables InnoDB et des colonnes `id` de type `INT`
   (adapter la migration sinon, comme indiqué dans le fichier).
3. **Migration** : exécuter le fichier de migration dans l'onglet SQL de phpMyAdmin.
4. **Déploiement du code** : envoyer les fichiers, en conservant le `config/config.php`
   existant. Il reste valide tel quel ; options facultatives (cf. `config/config.example.php`) :
   `APP_ENV`, `APP_TIMEZONE`, `MAIL_TRANSPORT`, `EVENT_RETENTION_DAYS`.
5. **Cron des rappels** : le cron natif des hébergements mutualisés OVH ne s'exécute qu'une
   fois par heure ; il faut donc conserver un service externe, par exemple cron-job.org.
   - URL : `https://agendday.castells-cyprien.ovh/cron/send_reminders.php`, toutes les 5 minutes ;
   - en-tête HTTP : `X-Cron-Secret: <valeur de CRON_SECRET>` (dans cron-job.org :
     *Advanced › Headers*), puis retirer `?secret=` de l'URL ;
   - contrôle sans effet : ajouter `?dry-run=1` à l'URL, la réponse commence par `[simulation]`.
6. **Purge quotidienne** : `cron/delete_past_events.php`, une fois par jour, soit via le
   même service HTTP, soit via le cron natif OVH en mode PHP (script `www/cron/delete_past_events.php`).
7. **Vérifications** : connexion, création d'un événement avec un rappel à 5 minutes,
   apparition de la notification dans la cloche, réception de l'e-mail.

**Retour arrière** : redéployer l'ancien code suffit. Le schéma migré reste compatible
avec lui, et les nouvelles tables sont simplement ignorées.

### Déploiement automatique depuis GitHub (GitHub Actions)

Le workflow `.github/workflows/deploy.yml` lance les tests (PHP 8.2 + MySQL 8) à chaque
push et pull request. Si les tests passent, il envoie les fichiers modifiés de la
branche `main` par FTPS. Les dossiers de développement (`tests/`, `dev/`, `docs/`,
`database/`…) ne sont pas envoyés et `config/config.php` n'est jamais touché.

Mise en place :
1. GitHub › *Settings › Secrets and variables › Actions › Secrets* : créer `FTP_SERVER`,
   `FTP_USERNAME`, `FTP_PASSWORD` (Espace client OVH › Hébergement › onglet *FTP-SSH*).
2. Exécuter la migration SQL en production (étapes 1 à 3 ci-dessus).
3. Onglet *Variables* : créer `DEPLOY_ENABLED` = `true`. Tant que cette variable n'existe
   pas, le workflow ne fait que lancer les tests. Si le site n'est pas dans `www/`,
   créer aussi `FTP_SERVER_DIR`.
4. Pousser sur `main`, ou lancer manuellement *Actions › Tests et déploiement › Run workflow*.

Pour une migration SQL ultérieure : désactiver `DEPLOY_ENABLED`, migrer, réactiver, puis
pousser. Les migrations ne sont jamais exécutées automatiquement.

Alternative : l'intégration Git native d'OVH (onglet *Multisite*). Elle exige un dossier
d'installation vide, déploie tout le dépôt sans lancer les tests, et place le dossier
`.git` dans le site (bloqué par le `.htaccess`). Elle est moins adaptée ici.

## 4. Tests et vérifications réalisés

| Vérification | Résultat |
|---|---|
| `php tests/run.php` : 52 tests (unitaires, MySQL, HTTP de bout en bout) | ✅ 52 réussis, 0 échec |
| `php -l` sur tous les fichiers PHP du projet | ✅ 0 erreur |
| `node --check` sur tous les fichiers JavaScript | ✅ |
| Parcours manuels dans le navigateur intégré (Chromium) : création, validation, double clic, modification de série, suppression confirmée, abandon de saisie, notification, recherche, filtres, vue liste, navigation clavier | ✅ |
| Affichage 320 px, 375 px, tablette, bureau ; thèmes clair et sombre ; absence de défilement horizontal | ✅ (2 débordements trouvés à 320 px puis corrigés) |

**Non vérifié** :
- comportement réel d'Apache / `.htaccess` (le serveur PHP intégré ne le lit pas ; ses règles
  sont reproduites dans `dev/router.php`) ;
- envoi SMTP réel (simulé ou journalisé dans les tests) ;
- PHP 8.0 / 8.1 (le code évite les fonctionnalités postérieures à 8.0, mais n'a été exécuté qu'en 8.2) ;
- Firefox et Safari ;
- lecteurs d'écran réels (structure ARIA contrôlée dans le DOM uniquement).

## 5. Limites connues et pistes

- **Récurrence** : une modification ou suppression s'applique à toute la série ; pas
  d'exception sur une seule occurrence. Pas d'événement sur plusieurs jours, pas de vue semaine.
- **Précision des rappels** : dépend de la fréquence du cron externe (≈ 5 min).
- **Délivrabilité** : pas de suivi des rebonds ; configurer SPF/DKIM sur le domaine d'envoi OVH.
- **Fuseau horaire** unique pour tous les utilisateurs (`APP_TIMEZONE`).
- **Notifications push du navigateur** : non implémentées (service worker, clés VAPID et
  consentement à prévoir).
- **Ressources tierces** : Font Awesome (cdnjs) et Inter (Google Fonts) sont chargés depuis
  des CDN ; les héberger localement supprimerait ces requêtes tierces.
- Les captures d'écran de la page d'accueil (`assets/images/agendday-preview-*.webp`)
  montrent encore l'ancienne interface et sont à refaire.
- L'inscription indique encore si une adresse est déjà utilisée (comportement existant conservé).
- ⚠️ Le `config/config.php` de votre poste pointe vers une base distante : lancer
  l'application en local avec ce fichier écrirait dans cette base. Utilisez `php dev/serve.php`,
  qui s'appuie sur une base locale dédiée.
