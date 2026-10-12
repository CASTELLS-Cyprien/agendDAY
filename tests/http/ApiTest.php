<?php
declare(strict_types=1);

/*
 * Parcours de bout en bout via le serveur PHP intégré (sessions, CSRF, API JSON,
 * cron HTTP). Les tests de cette suite s'enchaînent et partagent leur état.
 */

/** @var Tester $t */
$t->suite('Parcours HTTP de bout en bout (serveur PHP intégré)');

$ctx = new stdClass();

$t->beforeEach(function () use ($ctx) {
    if (!TestDatabase::available()) {
        throw new SkipTest('serveur MySQL local indisponible');
    }
    if (!isset($ctx->ready)) {
        $ctx->pdo   = TestDatabase::reset();
        $ctx->alice = TestDatabase::createUser($ctx->pdo, 'alice@agendday.test', 'Alice');
        $ctx->bob   = TestDatabase::createUser($ctx->pdo, 'bob@agendday.test', 'Bob');
        TestServer::start();
        $ctx->a     = new HttpClient();
        $ctx->b     = new HttpClient();
        $ctx->ready = true;
    }
});

$day = fn (int $offset): string => (new DateTimeImmutable("today {$offset} days"))->format('Y-m-d');

$t->test('pages publiques, accès protégés, fichiers internes inaccessibles', function () use ($ctx, $day) {
    $guest = new HttpClient();
    Assert::same(200, $guest->get('/')->status);

    $protected = $guest->get('/calendrier');
    Assert::same(302, $protected->status);
    Assert::same('/connexion', $protected->header('location'));

    $api = $guest->get('/api/events?from=' . $day(0) . '&to=' . $day(1));
    Assert::same(401, $api->status);
    Assert::same('unauthenticated', $api->json()['code']);

    Assert::same(403, $guest->get('/app/Models/EventModel.php')->status);
    Assert::same(403, $guest->get('/config/config.example.php')->status);
    Assert::same(403, $guest->get('/tests/config.testing.php')->status);

    $missing = $guest->get('/page-inexistante');
    Assert::same(404, $missing->status);
    Assert::contains('introuvable', $missing->body);
    Assert::same(404, $guest->get('/api/inexistant')->status);
    Assert::same(405, $guest->get('/api/events/delete')->status);
});

$t->test('connexion : mot de passe erroné refusé, puis session ouverte', function () use ($ctx) {
    $ctx->a->page('/connexion');
    $refused = $ctx->a->postWithCsrf('/connexion', ['email' => 'alice@agendday.test', 'motDePasse' => 'mauvais']);
    Assert::same('/connexion', $refused->header('location'));
    Assert::contains('alice@agendday.test', $ctx->a->page('/connexion')->body, 'E-mail conservé après erreur');

    $ok = $ctx->a->login('alice@agendday.test', TestDatabase::PASSWORD);
    Assert::same('/calendrier', $ok->header('location'));

    $page = $ctx->a->page('/calendrier');
    Assert::same(200, $page->status);
    Assert::contains('Alice', $page->body);
    Assert::true($ctx->a->csrf !== null, 'Jeton CSRF présent dans la page');
});

$t->test('après connexion, retour à la page demandée (lien d\'un e-mail)', function () {
    $client = new HttpClient();
    Assert::same(302, $client->get('/calendrier?date=2026-10-20&event=3')->status);
    $login = $client->login('alice@agendday.test', TestDatabase::PASSWORD);
    Assert::same('/calendrier?date=2026-10-20&event=3', $login->header('location'));
});

$t->test('API : jeton CSRF exigé, erreurs de validation par champ', function () use ($ctx, $day) {
    $noToken = $ctx->a->post('/api/events', ['title' => 'Sans jeton', 'date' => $day(1), 'time' => '10:00']);
    Assert::same(403, $noToken->status);
    Assert::same('csrf', $noToken->json()['code']);

    $invalid = $ctx->a->postWithCsrf('/api/events', ['title' => ' ', 'date' => '2026-02-30', 'time' => '25:00']);
    Assert::same(422, $invalid->status);
    $fields = $invalid->json()['fields'];
    Assert::true(isset($fields['title'], $fields['date'], $fields['time']));
});

$t->test('création, lecture par période, déplacement et suppression d\'un événement', function () use ($ctx, $day) {
    $payload = '<img src=x onerror=alert(1)>"Réunion';
    $created = $ctx->a->postWithCsrf('/api/events', [
        'title'     => $payload,
        'date'      => $day(2),
        'time'      => '10:00',
        'endTime'   => '11:30',
        'category'  => 'work',
        'reminders' => ['60', '15'],
    ]);
    Assert::same(201, $created->status, $created->body);
    $event = $created->json()['event'];
    Assert::same([15, 60], $event['reminders']);
    Assert::same('11:30', $event['end']);
    $ctx->eventId = $event['id'];

    $range = $ctx->a->get('/api/events?from=' . $day(0) . '&to=' . $day(6));
    Assert::same('no-store', $range->header('cache-control'));
    Assert::contains('application/json', (string) $range->header('content-type'));
    Assert::same($payload, $range->json()['events'][0]['title'], 'Texte brut restitué (échappé côté client)');

    $moved = $ctx->a->postWithCsrf('/api/events/update', [
        'eventId' => $ctx->eventId, 'title' => 'Réunion', 'date' => $day(3), 'time' => '14:00', 'category' => 'work',
    ]);
    Assert::same(200, $moved->status, $moved->body);
    Assert::count(0, $ctx->a->get("/api/events?from={$day(2)}&to={$day(2)}")->json()['events']);
    $after = $ctx->a->get("/api/events?from={$day(3)}&to={$day(3)}")->json()['events'];
    Assert::same('14:00', $after[0]['start']);
    Assert::same([], $after[0]['reminders'], 'Rappels retirés lorsqu\'ils ne sont plus cochés');

    Assert::same(400, $ctx->a->get("/api/events?from={$day(0)}&to={$day(200)}")->status, 'Période trop longue');
});

$t->test('isolation : un autre utilisateur ne voit ni ne modifie l\'événement', function () use ($ctx, $day) {
    $ctx->b->login('bob@agendday.test', TestDatabase::PASSWORD);

    Assert::count(0, $ctx->b->get("/api/events?from={$day(0)}&to={$day(30)}")->json()['events']);
    $update = $ctx->b->postWithCsrf('/api/events/update', [
        'eventId' => $ctx->eventId, 'title' => 'Piraté', 'date' => $day(3), 'time' => '14:00',
    ]);
    Assert::same(404, $update->status);
    Assert::same(404, $ctx->b->postWithCsrf('/api/events/delete', ['eventId' => $ctx->eventId])->status);
    Assert::same('Réunion', $ctx->pdo->query('SELECT title FROM events WHERE id = ' . (int) $ctx->eventId)->fetchColumn());
});

$t->test('suppression, puis seconde suppression signalée', function () use ($ctx) {
    Assert::same(200, $ctx->a->postWithCsrf('/api/events/delete', ['eventId' => $ctx->eventId])->status);
    Assert::same(404, $ctx->a->postWithCsrf('/api/events/delete', ['eventId' => $ctx->eventId])->status);
});

$t->test('recherche : texte partiel, insensible à la casse, prochaine occurrence d\'une série', function () use ($ctx, $day) {
    $ctx->a->postWithCsrf('/api/events', ['title' => 'Dentiste', 'date' => $day(5), 'time' => '09:00']);
    $ctx->a->postWithCsrf('/api/events', [
        'title' => 'Cours de piano', 'date' => $day(-14), 'time' => '18:00', 'recurrence' => 'weekly',
    ]);

    $results = $ctx->a->get('/api/events/search?q=DENT')->json()['results'];
    Assert::count(1, $results);
    Assert::same('Dentiste', $results[0]['title']);

    $piano = $ctx->a->get('/api/events/search?q=piano')->json()['results'][0];
    Assert::same($day(0), $piano['date'], 'Prochaine occurrence hebdomadaire = aujourd\'hui');

    Assert::same(400, $ctx->a->get('/api/events/search?q=x')->status);
});

$t->test('cron HTTP : secret exigé, notification créée, e-mail journalisé', function () use ($ctx, $day) {
    $mailDir = MAIL_LOG_DIR;
    array_map('unlink', glob($mailDir . '/*.eml') ?: []);

    $created = $ctx->a->postWithCsrf('/api/events', [
        'title' => 'Rendez-vous banque', 'date' => $day(1), 'time' => '10:00', 'reminders' => ['15'],
    ]);
    Assert::same(201, $created->status);
    // Avance l'échéance du rappel pour simuler l'heure venue
    $ctx->pdo->prepare('UPDATE event_reminders SET remind_at = :past WHERE event_id = :id')->execute([
        ':past' => (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'),
        ':id'   => $created->json()['event']['id'],
    ]);

    $guest = new HttpClient();
    Assert::same(403, $guest->get('/cron/send_reminders.php')->status);
    Assert::same(403, $guest->get('/cron/send_reminders.php?secret=mauvais')->status);

    $preview = $guest->get('/cron/send_reminders.php?dry-run=1', ['X-Cron-Secret: ' . CRON_SECRET]);
    Assert::contains('[simulation] 1 rappel(s) à échéance', $preview->body);

    $run = $guest->get('/cron/send_reminders.php', ['X-Cron-Secret: ' . CRON_SECRET]);
    Assert::same(200, $run->status, $run->body);
    Assert::contains('1 notification(s) créée(s)', $run->body);
    Assert::contains('1 accepté(s) par le serveur SMTP', $run->body);

    $mails = glob($mailDir . '/*.eml') ?: [];
    Assert::count(1, $mails);
    $raw = (string) file_get_contents($mails[0]);
    Assert::contains('alice@agendday.test', $raw);
    Assert::contains('Rendez-vous banque', quoted_printable_decode($raw));

    $again = $guest->get('/cron/send_reminders.php', ['X-Cron-Secret: ' . CRON_SECRET]);
    Assert::contains('OK — 0 notification(s)', $again->body, 'Aucun doublon au passage suivant');
});

$t->test('centre de notifications : non lues, lecture, cloisonnement', function () use ($ctx) {
    $list = $ctx->a->get('/api/notifications')->json();
    Assert::same(1, $list['unread']);
    Assert::same('Rendez-vous banque', $list['items'][0]['title']);
    Assert::same('sent', $list['items'][0]['emailStatus']);
    $id = $list['items'][0]['id'];

    Assert::same(404, $ctx->b->postWithCsrf('/api/notifications/read', ['id' => $id])->status, 'Notification d\'autrui');
    Assert::same(0, $ctx->a->postWithCsrf('/api/notifications/read', ['id' => $id])->json()['unread']);
    Assert::same(0, $ctx->b->get('/api/notifications')->json()['unread']);
});

$t->test('préférences de notification enregistrées et validées', function () use ($ctx) {
    $ctx->a->page('/parametres');
    $saved = $ctx->a->postWithCsrf('/parametres', ['default_reminder' => '60']); // case e-mail décochée
    Assert::same('/parametres', $saved->header('location'));
    $prefs = $ctx->pdo->query('SELECT email_reminders, default_reminder_minutes FROM users WHERE id = ' . $ctx->alice)->fetch();
    Assert::same(0, (int) $prefs['email_reminders']);
    Assert::same(60, (int) $prefs['default_reminder_minutes']);

    $ctx->a->postWithCsrf('/parametres', ['email_reminders' => '1', 'default_reminder' => '7']);
    Assert::contains('Rappel par défaut invalide', $ctx->a->page('/parametres')->body);
    Assert::same(60, (int) $ctx->pdo->query('SELECT default_reminder_minutes FROM users WHERE id = ' . $ctx->alice)->fetchColumn());
});

$t->test('déconnexion : uniquement en POST avec jeton CSRF', function () use ($ctx) {
    Assert::same('/calendrier', $ctx->a->get('/deconnexion')->header('location'));
    Assert::same(200, $ctx->a->get('/calendrier')->status, 'Un lien GET ne déconnecte plus');

    $ctx->a->post('/deconnexion');
    Assert::same(200, $ctx->a->get('/calendrier')->status, 'Sans jeton : refusé');

    $ctx->a->page('/calendrier');
    Assert::same('/connexion', $ctx->a->postWithCsrf('/deconnexion')->header('location'));
    Assert::same(302, $ctx->a->get('/calendrier')->status);
});

$t->test('suppression de compte : mot de passe exigé, données effacées', function () use ($ctx) {
    $ctx->b->postWithCsrf('/api/events', ['title' => 'Événement de Bob', 'date' => '2026-12-01', 'time' => '10:00']);
    $ctx->b->page('/parametres');

    $ctx->b->postWithCsrf('/parametres/supprimer-compte', ['password' => 'mauvais', 'confirm' => '1']);
    Assert::same(1, (int) $ctx->pdo->query('SELECT COUNT(*) FROM users WHERE id = ' . $ctx->bob)->fetchColumn());

    $ctx->b->page('/parametres');
    $deleted = $ctx->b->postWithCsrf('/parametres/supprimer-compte', ['password' => TestDatabase::PASSWORD, 'confirm' => '1']);
    Assert::same('/connexion', $deleted->header('location'));
    Assert::same(0, (int) $ctx->pdo->query('SELECT COUNT(*) FROM users WHERE id = ' . $ctx->bob)->fetchColumn());
    Assert::same(0, (int) $ctx->pdo->query('SELECT COUNT(*) FROM events WHERE userID = ' . $ctx->bob)->fetchColumn());
    Assert::same(302, $ctx->b->get('/calendrier')->status);
});
