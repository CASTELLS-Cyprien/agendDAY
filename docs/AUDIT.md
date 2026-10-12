# Audit d'agendDAY — état des lieux et plan d'amélioration

> Audit réalisé le 12/10/2026 sur la branche `main` (commit `10a4ff8`), à partir du code
> du dépôt uniquement. Aucune requête n'a été envoyée à la base de production.
> Aucun secret n'est reproduit dans ce document.

## 1. Architecture réelle

```
index.php ─► Core\Router (correspondance exacte méthode + chemin)
               ├─ App\Controllers\*      (Home, Auth, Calendar, Event [API JSON], Contact, Page)
               ├─ App\Models\*           (BaseModel = PDO singleton, EventModel, UserModel)
               ├─ App\Services\MailService (PHPMailer 7, SMTP OVH)
               └─ app/Views/{layouts,…}  (PHP natif, 3 gabarits : base / minimal / app)
cron/send_reminders.php     ─► appelé par HTTP (?secret=…) depuis cron-job.org, ou en CLI
cron/delete_past_events.php ─► idem, quotidien
assets/js/calendar.js       ─► classe CalendarApp (rendu du mois, modale, appels fetch)
```

- **Stack** : PHP 8.x (typage strict), MySQL via PDO (requêtes préparées, `EMULATE_PREPARES=false`),
  JavaScript natif, CSS avec variables, Font Awesome et Inter via CDN, `vendor/` versionné
  (déploiement sans Composer sur OVH).
- **Configuration** : `config/config.php` (non versionné) définit des constantes.
  ⚠️ La copie locale de ce fichier pointe vers une base **distante** : lancer l'application
  en local écrit potentiellement dans la base de production.
- **Schéma** : aucun fichier de schéma dans le dépôt. Il a été reconstitué à partir des requêtes :
  - `users(id, nomUtilisateur, email, motDePasse, confirmation_token, confirmation_token_expiration, is_confirmed, reset_token, reset_token_expiration)`
  - `events(id, title, time, descriptionEvent, userID, dateEvent, sentReminder)`
  - aucune contrainte de clé étrangère connue.

## 2. Fonctionnalités opérationnelles (vérifiées dans le code)

| Fonctionnalité | État |
|---|---|
| Inscription avec confirmation par e-mail (jeton haché, expiration 24 h) | ✅ |
| Connexion (bcrypt, anti-énumération par timing, limitation de débit, régénération de session) | ✅ |
| Mot de passe oublié / réinitialisation (jeton haché, 1 h) | ✅ |
| Calendrier mensuel, navigation, « Aujourd'hui », saut à un mois (mm/aaaa) | ✅ |
| Création, modification (titre, heure, description) et suppression d'un événement | ✅ |
| Contrôle d'accès des événements par `userID` sur chaque requête | ✅ |
| CSRF sur les formulaires et l'API d'écriture | ✅ |
| Rappel par e-mail, déclenché par le cron HTTP toutes les 5 min (si cron-job.org est configuré) | ✅ partiel |
| Thème clair/sombre, formulaire de contact avec reCAPTCHA | ✅ |

### Fonctionnement réel des rappels

1. Un service externe (cron-job.org, d'après les commentaires) appelle
   `https://…/cron/send_reminders.php?secret=…` toutes les 5 minutes. Le cron natif OVH
   mutualisé ne permet qu'une exécution par heure, ce qui explique ce choix.
2. Le script sélectionne les événements dont `date + heure` tombe dans **les 5 prochaines minutes**
   et dont `sentReminder = 0`, envoie un e-mail, puis passe `sentReminder` à 1.
3. Conséquences :
   - le « rappel » n'est **pas paramétrable** : il part entre 0 et 5 minutes avant l'heure ;
   - le champ s'intitule « Heure de rappel » alors qu'il est traité comme l'heure de l'événement ;
   - si un passage du cron est manqué (panne, lenteur), le rappel est **perdu silencieusement** ;
   - en cas d'échec SMTP, 3 tentatives avec `sleep(2)` dans la même requête HTTP, puis abandon définitif ;
   - le mode `?test=1` élargit la fenêtre à 24 h et **envoie réellement** les e-mails ;
   - le verrou par fichier (`touch`) n'est pas atomique ;
   - aucune trace consultable des envois (uniquement `error_log`).

## 3. Fonctionnalités incomplètes ou absentes

- Impossible de **changer la date** d'un événement existant (la date est forcée au jour sélectionné).
- Pas d'heure de fin, pas d'événement « journée entière », pas de lieu, de catégorie ni de récurrence.
- Pas de choix du délai de rappel, pas de rappels multiples, pas de préférences de notification.
- Pas de recherche, pas de liste des prochains événements, pas de centre de notifications.
- Pas de page 404/500 ; le routeur affiche un texte brut.
- Suppression de compte uniquement par e-mail au développeur (RGPD).

## 4. Principaux problèmes techniques

### Sécurité
| # | Problème | Gravité |
|---|---|---|
| S1 | **XSS** : `escapeHtml()` (via `div.innerHTML`) n'échappe pas les guillemets ; la description est injectée dans l'attribut `data-full-text="…"`. Une description contenant `"` casse l'attribut et permet d'injecter des attributs (`onmouseover`…). Exploitable uniquement sur ses propres données (self-XSS) mais réel. | Haute |
| S2 | Secret du cron transmis en **paramètre d'URL** (journalisé par les serveurs et le service tiers). | Moyenne |
| S3 | Aucun gestionnaire d'exceptions global ; `display_errors` dépend du serveur → risque de fuite d'informations, pages blanches. | Moyenne |
| S4 | CSP avec `'unsafe-inline'` pour les scripts (scripts inline dans les gabarits et la page contact). | Moyenne |
| S5 | Déconnexion en GET (CSRF de déconnexion), `session.use_strict_mode` non activé, jeton CSRF non renouvelé à la connexion. | Faible |
| S6 | `.htaccess` ne bloque pas `database/`, les fichiers cachés (`.git` si déployé par git) ni `composer.json`. | Faible |
| S7 | Notifications JS construites avec `innerHTML` à partir des messages serveur. | Faible |

### Fiabilité et données
| # | Problème | Gravité |
|---|---|---|
| D1 | **Perte de données** : `delete_past_events.php` supprime chaque jour **tous** les événements passés — l'historique disparaît le lendemain. | Haute |
| D2 | Fuseaux horaires incohérents : le cron force `Europe/Paris`, l'application web non ; les comparaisons mélangent l'heure PHP et `NOW()` MySQL. | Moyenne |
| D3 | Rappels perdus si le cron est en retard ; pas de reprise ; pas d'idempotence garantie par la base. | Haute |
| D4 | `GET /api/events` charge **tous** les événements de l'utilisateur à chaque modification. | Faible (croissance) |
| D5 | Longueurs validées en octets (`strlen`) : un titre accentué est refusé avant 200 caractères. | Faible |
| D6 | Les messages de succès (« Inscription réussie ! ») s'affichent avec le style d'erreur rouge. | Faible |

### UX et accessibilité
| # | Problème |
|---|---|
| A1 | Navigation clavier cassée : chaque flèche reconstruit la grille, le focus est perdu après le premier déplacement ; Entrée/Espace sans effet malgré `role="button"`. |
| A2 | `role="grid"` sans lignes ni cellules ; 42 arrêts de tabulation. |
| A3 | « Aujourd'hui » (blanc sur `#6c7ce0`, contraste 3,7:1) et « sélectionné » sont deux bleus quasi identiques. |
| A4 | Le menu latéral masqué hors écran reste atteignable au clavier ; bouton sans `aria-expanded`. |
| A5 | Modale sans piège de focus ni restitution du focus ; notifications non annoncées (pas d'`aria-live`). |
| A6 | Les événements ne sont pas visibles dans la grille (un simple point) ; aucune vue d'ensemble. |
| A7 | Page d'accueil : témoignages fictifs et revendications non fondées (« des milliers d'utilisateurs », « synchronisation »). |

## 5. Améliorations fonctionnelles retenues

Priorisées selon l'usage réel d'un agenda personnel et la complexité raisonnable du projet :

- Modèle d'événement enrichi : date modifiable, début/fin, journée entière, lieu, catégorie (libellé + couleur), récurrence simple (quotidienne, hebdomadaire, mensuelle, annuelle, avec date de fin).
- Rappels configurables : plusieurs délais par événement (à l'heure, 5/15/30 min, 1/2 h, 1 jour, 1 semaine), rappel par défaut et activation des e-mails dans les préférences.
- Moteur de rappels idempotent : planification en base (`event_reminders`), une notification unique par occurrence (contrainte `UNIQUE`), reprise des retards tant que l'événement n'a pas commencé, nouvelles tentatives SMTP espacées, statut d'envoi consultable.
- Centre de notifications in-app alimenté par ce même moteur (aucune notification simulée).
- Calendrier : événements visibles dans les cases, panneau du jour, « À venir », vue liste, recherche, filtres par catégorie, détails d'un événement avec actions rapides (modifier, dupliquer, supprimer).
- Préférences et suppression de compte en libre-service.

Écartés volontairement : vue semaine (peu de valeur ajoutée par rapport au panneau du jour + vue liste, coût élevé sur mobile), événements sur plusieurs jours, exceptions de récurrence, notifications push (permissions, service worker, VAPID : à envisager plus tard).

## 6. Risques de régression

| Risque | Mesure |
|---|---|
| Le `config.php` de production ne contient pas les nouvelles constantes | Valeurs par défaut dans `bootstrap.php` : un `config.php` existant reste valide. |
| Migration SQL sur une structure de production non documentée | Migration additive uniquement (aucun `DROP`/`MODIFY`), vérifications préalables documentées, testée sur un schéma reconstitué. |
| URL du cron déjà configurée sur cron-job.org | Noms de fichiers conservés ; `?secret=` toujours accepté (en-tête recommandé). |
| Changement du comportement de purge | Durée de conservation configurable (`EVENT_RETENTION_DAYS`, `0` = ancien comportement). |
| Rappels déjà programmés au moment du déploiement | La migration crée un rappel « 5 min avant » pour chaque événement futur non rappelé (équivalent de l'ancien comportement). |
| Ancien JavaScript en cache | Les URL d'assets sont versionnées par `filemtime`. |

## 7. Plan priorisé

| Prio | Action | Impact | Complexité | Dépendances |
|---|---|---|---|---|
| **P0** | Corriger le XSS de la liste d'événements (construction DOM sans `innerHTML`) | Élevé | Faible | — |
| **P0** | Remplacer la purge quotidienne par une durée de conservation configurable | Élevé | Faible | — |
| **P0** | `bootstrap.php` commun : fuseau horaire unique (PHP + session MySQL), gestion centralisée des erreurs, pages 404/500 | Élevé | Faible | — |
| **P0** | Cron : secret par en-tête/CLI, suppression du mode test qui envoie, verrou global `GET_LOCK` | Élevé | Faible | bootstrap |
| **P0** | Durcissement : CSP sans `unsafe-inline` pour les scripts, déconnexion en POST, `use_strict_mode`, `.htaccess` | Moyen | Faible | — |
| **P1** | Migration SQL additive (colonnes d'événement, `event_reminders`, `notifications`, préférences) | Élevé | Moyenne | — |
| **P1** | Moteur de rappels idempotent + journal d'envoi + reprises | Élevé | Moyenne | migration |
| **P1** | API par plage de dates, validation serveur par champ, transactions | Élevé | Moyenne | migration |
| **P1** | Nouveau calendrier accessible (grille ARIA, focus itinérant, dialogues natifs, annonces) | Élevé | Élevée | API |
| **P1** | Formulaire d'événement complet, conservation des saisies, protection double soumission | Élevé | Moyenne | API |
| **P1** | Centre de notifications + page Préférences | Élevé | Moyenne | moteur de rappels |
| **P2** | Design system (jetons, composants, contrastes AA, mode sombre, réduction des animations) | Moyen | Moyenne | — |
| **P2** | Recherche, filtres par catégorie, vue liste, « À venir », duplication | Moyen | Moyenne | API |
| **P2** | Accueil honnête, politique de confidentialité à jour, suppression de compte | Moyen | Faible | — |
| **P2** | Tests automatisés (unitaires, intégration MySQL, HTTP) et documentation | Élevé | Moyenne | tout |

Le bilan des modifications effectuées et des limites restantes figure dans [CHANGELOG-AMELIORATIONS.md](CHANGELOG-AMELIORATIONS.md).
