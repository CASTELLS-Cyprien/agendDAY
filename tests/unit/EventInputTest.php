<?php
declare(strict_types=1);

use App\Services\EventInput;

/** @var Tester $t */
$t->suite('Validation d\'un événement (unitaire)');

$valid = [
    'title'            => '  Réunion d\'équipe ',
    'date'             => '2026-10-12',
    'time'             => '09:30',
    'endTime'          => '10:30',
    'location'         => 'Salle B',
    'category'         => 'work',
    'descriptionEvent' => "Ordre du jour :\r\n- budget",
    'recurrence'       => 'weekly',
    'recurrenceUntil'  => '2026-12-31',
    'reminders'        => ['15', '1440', '15'],
];

$t->test('données valides normalisées', function () use ($valid) {
    ['data' => $data, 'errors' => $errors] = EventInput::fromRequest($valid);
    Assert::same([], $errors);
    Assert::same('Réunion d\'équipe', $data['title']);
    Assert::same('09:30', $data['time']);
    Assert::same('10:30', $data['end_time']);
    Assert::same("Ordre du jour :\n- budget", $data['description']);
    Assert::same([15, 1440], $data['reminders'], 'Rappels dédoublonnés et triés');
});

$t->test('titre obligatoire, longueur comptée en caractères (pas en octets)', function () use ($valid) {
    Assert::same('Indiquez un titre.', EventInput::fromRequest(['title' => '   '] + $valid)['errors']['title']);
    Assert::same([], EventInput::fromRequest(['title' => str_repeat('é', 200)] + $valid)['errors']);
    Assert::true(isset(EventInput::fromRequest(['title' => str_repeat('é', 201)] + $valid)['errors']['title']));
});

$t->test('date inexistante refusée', function () use ($valid) {
    Assert::true(isset(EventInput::fromRequest(['date' => '2026-02-30'] + $valid)['errors']['date']));
    Assert::true(isset(EventInput::fromRequest(['date' => '12/10/2026'] + $valid)['errors']['date']));
});

$t->test('heure de fin postérieure au début', function () use ($valid) {
    $errors = EventInput::fromRequest(['endTime' => '09:00'] + $valid)['errors'];
    Assert::same("L'heure de fin doit être postérieure à l'heure de début.", $errors['endTime']);
});

$t->test('journée entière : les heures sont ignorées', function () use ($valid) {
    ['data' => $data, 'errors' => $errors] = EventInput::fromRequest(['allDay' => '1', 'time' => 'nimporte'] + $valid);
    Assert::same([], $errors);
    Assert::true($data['all_day']);
    Assert::same('00:00', $data['time']);
    Assert::same(null, $data['end_time']);
});

$t->test('catégorie, répétition et rappels hors liste refusés', function () use ($valid) {
    $errors = EventInput::fromRequest([
        'category'   => 'admin',
        'recurrence' => 'hourly',
        'reminders'  => ['7'],
    ] + $valid)['errors'];
    Assert::true(isset($errors['category'], $errors['recurrence'], $errors['reminders']));
});

$t->test('cinq rappels au maximum', function () use ($valid) {
    $errors = EventInput::fromRequest(['reminders' => ['0', '5', '15', '30', '60', '120']] + $valid)['errors'];
    Assert::true(isset($errors['reminders']));
});

$t->test('fin de répétition postérieure à la date de l\'événement', function () use ($valid) {
    $errors = EventInput::fromRequest(['recurrenceUntil' => '2026-10-01'] + $valid)['errors'];
    Assert::true(isset($errors['recurrenceUntil']));
});

$t->test('caractères de contrôle supprimés, tableaux inattendus neutralisés', function () use ($valid) {
    ['data' => $data] = EventInput::fromRequest(['title' => "Ti\x00tre\nlong", 'location' => ['x']] + $valid);
    Assert::same('Ti tre long', $data['title']);
    Assert::same(null, $data['location']);
});
