# AgendDAY

Agenda en ligne : calendrier mensuel, événements récurrents, rappels par e-mail et
notifications dans l'application. PHP / MySQL / JavaScript natif, sans framework.

🔗 Démo : [agendday.castells-cyprien.ovh](https://agendday.castells-cyprien.ovh/)

## Fonctionnalités

- **Calendrier** : vue mensuelle avec les événements visibles dans chaque case, panneau du
  jour, liste des 14 prochains jours, vue liste, saut à un mois, bouton « Aujourd'hui ».
- **Événements** : titre, date, début / fin ou journée entière, lieu, description,
  catégorie (libellé + couleur), répétition quotidienne / hebdomadaire / mensuelle /
  annuelle avec date de fin. Création, modification, duplication, suppression confirmée.
- **Rappels** : jusqu'à 5 par événement (à l'heure, 5 / 15 / 30 min, 1 / 2 h, 1 jour,
  1 semaine avant), par e-mail et dans l'application ; rappel par défaut et activation des
  e-mails dans les préférences.
- **Centre de notifications** : compteur de non lues, historique, accès direct à
  l'événement, statut réel de l'e-mail (transmis, en attente, échec, annulé…).
- **Recherche** (titre, description, lieu) et **filtres par catégorie**.
- **Accessibilité** : grille ARIA navigable au clavier, dialogues natifs, annonces pour
  lecteurs d'écran, contrastes WCAG AA, respect de « réduire les animations ».
- **Compte** : inscription avec confirmation par e-mail, mot de passe oublié, suppression
  du compte et de toutes les données en libre-service.
- Thème clair / sombre (suit le système par défaut), interface responsive dès 320 px.

## Architecture

```
index.php            Front controller (sessions) → Core\Router
bootstrap.php        Configuration, fuseau horaire, erreurs, autoloaders (web + cron)
core/                Router, ErrorHandler, Cron (autorisation des scripts planifiés)
app/Controllers/     Pages et API JSON (/api/events, /api/notifications)
app/Models/          Accès MySQL via PDO (requêtes préparées, transactions)
app/Services/        Logique métier pure : Recurrence, ReminderScheduler, EventInput,
                     ReminderService (moteur de rappels), MailService (PHPMailer)
app/Views/           Gabarits PHP (layouts : base, minimal, app)
assets/              CSS (design system : global.css) et JavaScript (core, calendar…)
cron/                send_reminders.php (toutes les 5 min), delete_past_events.php (quotidien)
database/            schema.sql (installation neuve) et migrations/
tests/               Suite de tests sans dépendance : php tests/run.php
dev/                 Serveur et données de démonstration pour le développement local
```

### Fonctionnement des rappels

1. À l'enregistrement d'un événement, chaque rappel reçoit sa prochaine échéance
   (`event_reminders.remind_at`).
2. `cron/send_reminders.php`, appelé toutes les 5 minutes, crée une notification par
   échéance atteinte. La clé unique `(event_id, offset_minutes, occurrence_at)` garantit
   l'absence de doublon, même si deux exécutions se chevauchent.
3. Les e-mails en attente sont envoyés. En cas d'échec SMTP, jusqu'à 4 tentatives sont
   faites, espacées de 5 min, 15 min puis 1 h.
4. Un rappel en retard reste envoyé jusqu'à 15 minutes après le début de l'événement
   (fin de journée pour une journée entière), puis il est abandonné.
5. Si l'événement est modifié ou supprimé, ses rappels sont recalculés ou annulés, et
   un e-mail en attente pour une occurrence qui n'existe plus est annulé.

« Envoyé » signifie *accepté par le serveur SMTP* : la remise effective dans la boîte de
réception n'est pas vérifiable par l'application.

## Installation locale

Prérequis : PHP ≥ 8.0 (extensions pdo_mysql, mbstring, curl), MySQL 5.7+ ou MariaDB 10.3+.

```bash
php dev/seed.php
```

```bash
php dev/serve.php
```

`dev/seed.php` crée la base locale `agendday_dev` avec un compte de démonstration ;
l'identifiant et le mot de passe (tiré au hasard à chaque exécution) s'affichent dans
le terminal. `dev/serve.php` lance
l'application sur http://127.0.0.1:8000. En développement, les e-mails sont écrits dans
`storage/mail/` au lieu d'être envoyés (`MAIL_TRANSPORT = 'log'`).

Ces scripts utilisent `tests/config.testing.php` (MySQL local, utilisateur `root`) et ne
lisent jamais `config/config.php`.

## Tests

```bash
php tests/run.php
```

52 tests : unitaires (récurrence, validation, planification), intégration MySQL (moteur
de rappels, migration sur un schéma historique) et parcours HTTP de bout en bout
(sessions, CSRF, isolation des utilisateurs, cron). La base `agendday_test` est recréée à
chaque exécution ; les tests refusent toute base non locale.

Sur GitHub, ces tests sont relancés à chaque push par `.github/workflows/deploy.yml`
(PHP 8.2 + MySQL 8), qui peut aussi déployer automatiquement sur OVH.

## Configuration et déploiement

Voir [docs/CHANGELOG-AMELIORATIONS.md](docs/CHANGELOG-AMELIORATIONS.md) : migration SQL,
nouvelles options de `config/config.php`, configuration du cron, procédure de déploiement
et de retour arrière. L'audit initial est dans [docs/AUDIT.md](docs/AUDIT.md).

## Auteur

Développé par Cyprien Castells dans le cadre d'un projet personnel.
