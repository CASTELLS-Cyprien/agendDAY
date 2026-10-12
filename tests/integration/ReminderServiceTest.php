<?php
declare(strict_types=1);

use App\Models\EventModel;
use App\Models\NotificationModel;
use App\Services\EventInput;
use App\Services\ReminderMailer;
use App\Services\ReminderService;

/**
 * Faux expéditeur : enregistre les envois et peut simuler des échecs SMTP.
 */
final class FakeMailer implements ReminderMailer
{
    public array $sent = [];
    public int $calls = 0;

    public function __construct(private int $failuresBeforeSuccess = 0)
    {
    }

    public function sendReminder(string $email, string $userName, array $event, DateTimeImmutable $occurrence, string $relative): void
    {
        $this->calls++;
        if ($this->failuresBeforeSuccess > 0) {
            $this->failuresBeforeSuccess--;
            throw new RuntimeException('SMTP Error: Could not connect to SMTP host.');
        }
        $this->sent[] = ['email' => $email, 'title' => $event['title'], 'relative' => $relative, 'occurrence' => $occurrence];
    }
}

/** @var Tester $t */
$t->suite('Moteur de rappels (intégration MySQL)');

$state = new stdClass();

$t->beforeEach(function () use ($state) {
    if (!TestDatabase::available()) {
        throw new SkipTest('serveur MySQL local indisponible');
    }
    $state->pdo  = TestDatabase::reset();
    $state->user = TestDatabase::createUser($state->pdo, 'alice@agendday.test', 'Alice');
});

$at = fn (string $datetime): DateTimeImmutable => new DateTimeImmutable($datetime);

$createEvent = function (int $userId, array $input, DateTimeImmutable $now): int {
    ['data' => $data, 'errors' => $errors] = EventInput::fromRequest($input + [
        'title' => 'Réunion', 'date' => '2026-10-13', 'time' => '10:00', 'reminders' => ['15'],
    ]);
    Assert::same([], $errors, 'Données de test invalides');
    return (new EventModel())->create($userId, $data, $now);
};

$notifications = fn (PDO $pdo): array => $pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll();

$t->test('un rappel n\'est notifié et envoyé qu\'une seule fois', function () use ($state, $at, $createEvent, $notifications) {
    $createEvent($state->user, [], $at('2026-10-12 12:00'));
    $mailer  = new FakeMailer();
    $service = new ReminderService(null, null, $mailer);

    $stats = $service->run($at('2026-10-13 09:44'));
    Assert::same(0, $stats['notifications'], 'Trop tôt');

    $stats = $service->run($at('2026-10-13 09:46'));
    Assert::same(1, $stats['notifications']);
    Assert::same(1, $stats['emails_sent']);
    Assert::same('dans 14 minutes', $mailer->sent[0]['relative']);

    $service->run($at('2026-10-13 09:50'));
    $service->run($at('2026-10-13 09:51'));
    Assert::same(1, $mailer->calls, 'Aucun renvoi');

    $rows = $notifications($state->pdo);
    Assert::count(1, $rows);
    Assert::same('sent', $rows[0]['email_status']);
    Assert::same(null, $state->pdo->query('SELECT remind_at FROM event_reminders')->fetchColumn(), 'Plus rien à rappeler');
});

$t->test('la contrainte unique empêche les doublons même en cas d\'exécutions concurrentes', function () use ($state, $at, $createEvent) {
    $eventId = $createEvent($state->user, [], $at('2026-10-12 12:00'));
    $model   = new NotificationModel();
    $args    = [$state->user, $eventId, 15, $at('2026-10-13 10:00'), false, 'Réunion', 'pending', $at('2026-10-13 09:45')];
    Assert::true($model->createReminder(...$args));
    Assert::false($model->createReminder(...$args), 'Second insert ignoré');
    Assert::false($model->claimEmail(999999, $at('2026-10-13 09:45')));
    $id = (int) $state->pdo->query('SELECT id FROM notifications')->fetchColumn();
    Assert::true($model->claimEmail($id, $at('2026-10-13 09:45')));
    Assert::false($model->claimEmail($id, $at('2026-10-13 09:45')), 'Une seule réservation possible');
});

$t->test('échec SMTP : nouvelles tentatives espacées puis succès', function () use ($state, $at, $createEvent, $notifications) {
    $createEvent($state->user, ['date' => '2026-10-20', 'reminders' => ['1440']], $at('2026-10-12 12:00'));
    $mailer  = new FakeMailer(2);
    $service = new ReminderService(null, null, $mailer);

    $stats = $service->run($at('2026-10-19 10:00'));
    Assert::same(1, $stats['emails_retry']);
    Assert::same('pending', $notifications($state->pdo)[0]['email_status']);
    Assert::contains('Could not connect', (string) $notifications($state->pdo)[0]['email_error']);

    $service->run($at('2026-10-19 10:02'));
    Assert::same(1, $mailer->calls, 'Pas de nouvel essai avant le délai (5 min)');

    $service->run($at('2026-10-19 10:06'));
    Assert::same(2, $mailer->calls);
    $service->run($at('2026-10-19 10:22'));
    Assert::same(3, $mailer->calls);

    $row = $notifications($state->pdo)[0];
    Assert::same('sent', $row['email_status']);
    Assert::same(3, (int) $row['email_attempts']);
    Assert::same(null, $row['email_error']);
});

$t->test('échec définitif après le nombre maximal de tentatives', function () use ($state, $at, $createEvent, $notifications) {
    $createEvent($state->user, ['date' => '2026-10-25', 'reminders' => ['1440']], $at('2026-10-12 12:00'));
    $mailer  = new FakeMailer(100);
    $service = new ReminderService(null, null, $mailer);

    foreach (['2026-10-24 10:00', '2026-10-24 10:06', '2026-10-24 10:22', '2026-10-24 11:23', '2026-10-24 13:00', '2026-10-24 18:00'] as $time) {
        $service->run($at($time));
    }

    $row = $notifications($state->pdo)[0];
    Assert::same(NotificationModel::EMAIL_MAX_ATTEMPTS, $mailer->calls, 'Nombre d\'essais borné');
    Assert::same('failed', $row['email_status']);
});

$t->test('événement supprimé après programmation : e-mail annulé', function () use ($state, $at, $createEvent, $notifications) {
    $eventId = $createEvent($state->user, ['reminders' => ['15']], $at('2026-10-12 12:00'));
    $mailer  = new FakeMailer(1);
    $service = new ReminderService(null, null, $mailer);

    $service->run($at('2026-10-13 09:46'));              // échec SMTP, e-mail en attente
    Assert::true((new EventModel())->delete($eventId, $state->user));
    $service->run($at('2026-10-13 09:52'));

    $row = $notifications($state->pdo)[0];
    Assert::same(1, $mailer->calls);
    Assert::same('cancelled', $row['email_status']);
    Assert::same(null, $row['event_id'], 'Notification conservée mais détachée');
    Assert::same(0, (int) $state->pdo->query('SELECT COUNT(*) FROM event_reminders')->fetchColumn());
});

$t->test('événement déplacé : ancien rappel annulé, nouveau programmé', function () use ($state, $at, $createEvent, $notifications) {
    $eventId = $createEvent($state->user, ['reminders' => ['15']], $at('2026-10-12 12:00'));
    $mailer  = new FakeMailer(1);
    $service = new ReminderService(null, null, $mailer);
    $service->run($at('2026-10-13 09:46'));              // échec SMTP sur le rappel de 10 h

    ['data' => $data] = EventInput::fromRequest([
        'title' => 'Réunion', 'date' => '2026-10-13', 'time' => '11:00', 'reminders' => ['15'],
    ]);
    Assert::true((new EventModel())->update($eventId, $state->user, $data, $at('2026-10-13 09:47')));

    $service->run($at('2026-10-13 09:52'));
    Assert::same('cancelled', $notifications($state->pdo)[0]['email_status']);

    $service->run($at('2026-10-13 10:46'));
    $rows = $notifications($state->pdo);
    Assert::count(2, $rows);
    Assert::same('sent', $rows[1]['email_status']);
    Assert::same('2026-10-13 11:00:00', $rows[1]['occurrence_at']);
});

$t->test('rappel en retard délivré si l\'événement vient de commencer, expiré sinon', function () use ($state, $at, $createEvent, $notifications) {
    $createEvent($state->user, ['title' => 'A', 'time' => '10:00', 'reminders' => ['15']], $at('2026-10-12 12:00'));
    $createEvent($state->user, ['title' => 'B', 'time' => '08:00', 'reminders' => ['15']], $at('2026-10-12 12:00'));
    $mailer = new FakeMailer();

    $stats = (new ReminderService(null, null, $mailer))->run($at('2026-10-13 10:05'));
    Assert::same(1, $stats['notifications']);
    Assert::same(1, $stats['expired']);
    Assert::same('a commencé il y a 5 minutes', $mailer->sent[0]['relative']);
    Assert::count(1, $notifications($state->pdo));
});

$t->test('e-mails désactivés : notification in-app seulement', function () use ($state, $at, $createEvent, $notifications) {
    $state->pdo->exec('UPDATE users SET email_reminders = 0');
    $createEvent($state->user, [], $at('2026-10-12 12:00'));
    $mailer = new FakeMailer();

    (new ReminderService(null, null, $mailer))->run($at('2026-10-13 09:46'));
    Assert::same(0, $mailer->calls);
    Assert::same('disabled', $notifications($state->pdo)[0]['email_status']);
});

$t->test('série : le rappel est reprogrammé sur l\'occurrence suivante', function () use ($state, $at, $createEvent) {
    $createEvent($state->user, ['recurrence' => 'weekly', 'reminders' => ['60']], $at('2026-10-12 12:00'));
    $mailer = new FakeMailer();
    (new ReminderService(null, null, $mailer))->run($at('2026-10-13 09:01'));

    Assert::same(1, $mailer->calls);
    Assert::same('2026-10-20 09:00:00', $state->pdo->query('SELECT remind_at FROM event_reminders')->fetchColumn());
});

$t->test('envoi interrompu (verrou périmé) : remis en file', function () use ($state, $at, $createEvent, $notifications) {
    $eventId = $createEvent($state->user, [], $at('2026-10-12 12:00'));
    $model   = new NotificationModel();
    $model->createReminder($state->user, $eventId, 15, $at('2026-10-13 10:00'), false, 'Réunion', 'pending', $at('2026-10-13 09:30'));
    $id = (int) $state->pdo->query('SELECT id FROM notifications')->fetchColumn();
    $model->claimEmail($id, $at('2026-10-13 09:30'));

    Assert::same(0, $model->releaseStaleEmails($at('2026-10-13 09:40')), 'Verrou encore valide');
    Assert::same(1, $model->releaseStaleEmails($at('2026-10-13 09:46')));
    Assert::same('pending', $notifications($state->pdo)[0]['email_status']);
});

$t->test('contrôle d\'accès : impossible de modifier ou supprimer l\'événement d\'un autre', function () use ($state, $at, $createEvent) {
    $bob     = TestDatabase::createUser($state->pdo, 'bob@agendday.test', 'Bob');
    $eventId = $createEvent($state->user, [], $at('2026-10-12 12:00'));
    ['data' => $data] = EventInput::fromRequest(['title' => 'Piraté', 'date' => '2026-10-13', 'time' => '10:00']);

    Assert::false((new EventModel())->update($eventId, $bob, $data, $at('2026-10-12 12:00')));
    Assert::false((new EventModel())->delete($eventId, $bob));
    Assert::same(null, (new EventModel())->findForUser($eventId, $bob));
    Assert::same('Réunion', (new EventModel())->findForUser($eventId, $state->user)['title']);
});

$t->test('purge de conservation : séries sans fin et événements récents conservés', function () use ($state, $at, $createEvent) {
    $createEvent($state->user, ['title' => 'Ancien', 'date' => '2024-01-10'], $at('2024-01-01 12:00'));
    $createEvent($state->user, ['title' => 'Récent', 'date' => '2026-09-10'], $at('2026-09-01 12:00'));
    $createEvent($state->user, ['title' => 'Série sans fin', 'date' => '2023-01-02', 'recurrence' => 'weekly'], $at('2023-01-01 12:00'));
    $createEvent($state->user, ['title' => 'Série terminée', 'date' => '2023-01-02', 'recurrence' => 'weekly', 'recurrenceUntil' => '2023-06-30'], $at('2023-01-01 12:00'));

    Assert::same(2, (new EventModel())->purgeEndedBefore('2025-10-12'));
    $titles = $state->pdo->query('SELECT title FROM events ORDER BY title')->fetchAll(PDO::FETCH_COLUMN);
    Assert::same(['Récent', 'Série sans fin'], $titles);
});
