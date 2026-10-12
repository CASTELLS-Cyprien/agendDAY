<?php
declare(strict_types=1);

/*
 * Données de démonstration — DÉVELOPPEMENT UNIQUEMENT.
 *
 *   php dev/seed.php
 *
 * Recrée la base locale « agendday_dev » (schéma complet), puis crée un compte
 * de démonstration et des événements autour de la date du jour.
 *
 * Compte de démonstration : DEMO_EMAIL ci-dessous. Le mot de passe est tiré au
 * hasard à chaque exécution et affiché dans le terminal ; pour le fixer, définir la
 * variable d'environnement AGENDDAY_DEMO_PASSWORD avant de lancer le script.
 */

require __DIR__ . '/env.php';
require dirname(__DIR__) . '/bootstrap.php';

use App\Models\BaseModel;
use App\Models\EventModel;
use App\Services\EventInput;
use App\Services\ReminderService;

const DEMO_EMAIL = 'demo@agendday.test';

$demoPassword = getenv('AGENDDAY_DEMO_PASSWORD') ?: bin2hex(random_bytes(6));

if (!in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true) || !str_ends_with(DB_NAME, '_dev')) {
    fwrite(STDERR, "Refus : ce script ne s'exécute que sur une base locale dont le nom se termine par _dev.\n");
    exit(1);
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
$pdo->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');

$schema = (string) preg_replace('/--\s.*$/m', '', (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
foreach (preg_split('/;\s*(?:\R|$)/', $schema) as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}
BaseModel::resetConnection();

$pdo->prepare(
    'INSERT INTO users (nomUtilisateur, email, motDePasse, is_confirmed) VALUES (:name, :email, :hash, 1)'
)->execute([':name' => 'Camille', ':email' => DEMO_EMAIL, ':hash' => password_hash($demoPassword, PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

$now   = new DateTimeImmutable();
$day   = fn (int $offset): string => $now->modify("{$offset} days")->format('Y-m-d');
$model = new EventModel();

$events = [
    ['title' => "Réunion d'équipe", 'date' => $day(0), 'time' => '09:00', 'endTime' => '10:00', 'category' => 'work',
        'location' => 'Salle Kepler', 'recurrence' => 'weekly', 'reminders' => ['15']],
    ['title' => 'Déjeuner avec Léa', 'date' => $day(0), 'time' => '12:30', 'endTime' => '14:00', 'category' => 'personal',
        'location' => 'Café des Arts'],
    ['title' => 'Point projet AgendDAY', 'date' => $day(0), 'time' => '16:00', 'endTime' => '16:30', 'category' => 'work'],
    ['title' => 'Cours de piano', 'date' => $day(-21), 'time' => '18:00', 'endTime' => '19:00', 'category' => 'study',
        'recurrence' => 'weekly', 'reminders' => ['60']],
    ['title' => 'Dentiste', 'date' => $day(1), 'time' => '08:30', 'category' => 'health',
        'location' => '12 rue de la Paix', 'reminders' => ['60', '1440']],
    ['title' => 'Rendre le rapport de stage', 'date' => $day(3), 'allDay' => '1', 'category' => 'important',
        'descriptionEvent' => "Version PDF + annexes.\nEnvoyer au tuteur avant midi.", 'reminders' => ['1440', '10080']],
    ['title' => 'Anniversaire de Léa', 'date' => $day(5), 'allDay' => '1', 'category' => 'personal', 'recurrence' => 'yearly'],
    ['title' => 'Revue de sprint', 'date' => $day(6), 'time' => '10:00', 'endTime' => '11:00', 'category' => 'work'],
    ['title' => 'Course à pied', 'date' => $day(6), 'time' => '07:00', 'category' => 'health'],
    ['title' => 'Partiel de statistiques', 'date' => $day(6), 'time' => '14:00', 'endTime' => '16:00', 'category' => 'study'],
    ['title' => 'Apéro de départ de Marc', 'date' => $day(6), 'time' => '18:30', 'category' => 'personal'],
    ['title' => 'Atelier accessibilité', 'date' => $day(-4), 'time' => '14:00', 'endTime' => '17:00', 'category' => 'work'],
    ['title' => 'Vaccin', 'date' => $day(-9), 'time' => '11:15', 'category' => 'health'],
    ['title' => 'Facture électricité', 'date' => $day(12), 'allDay' => '1', 'category' => 'general', 'recurrence' => 'monthly'],
];

foreach ($events as $input) {
    ['data' => $data, 'errors' => $errors] = EventInput::fromRequest($input + ['time' => '09:00']);
    if ($errors !== []) {
        fwrite(STDERR, 'Donnée de démonstration invalide (' . $input['title'] . ') : ' . implode(' ', $errors) . PHP_EOL);
        exit(1);
    }
    $model->create($userId, $data, $now);
}

// Simule l'arrivée d'un rappel (comme le ferait le cron) pour alimenter le centre
// de notifications ; l'e-mail est écrit dans storage/mail (MAIL_TRANSPORT = log).
$pdo->prepare(
    "UPDATE event_reminders r JOIN events e ON e.id = r.event_id
     SET r.remind_at = :past, r.occurrence_at = TIMESTAMP(e.dateEvent, e.time)
     WHERE e.title = 'Dentiste' AND r.offset_minutes = 1440"
)->execute([':past' => $now->modify('-1 minute')->format('Y-m-d H:i:s')]);
$stats = (new ReminderService())->run($now);

echo 'Base ' . DB_NAME . ' prête : ' . count($events) . ' événements, '
    . $stats['notifications'] . ' notification(s).' . PHP_EOL;
echo 'Compte de démonstration : ' . DEMO_EMAIL . ' / mot de passe : ' . $demoPassword . PHP_EOL;
