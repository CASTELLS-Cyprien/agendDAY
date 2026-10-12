<?php
declare(strict_types=1);

use App\Services\DateText;
use App\Services\ReminderScheduler;

/** @var Tester $t */
$t->suite('Planification des rappels (unitaire)');

$event = fn (array $overrides = []): array => $overrides + [
    'dateEvent'        => '2026-10-13',
    'time'             => '10:00',
    'all_day'          => 0,
    'recurrence'       => 'none',
    'recurrence_until' => null,
];
$at = fn (string $datetime): DateTimeImmutable => new DateTimeImmutable($datetime);

$t->test('rappel 15 min avant un événement futur', function () use ($event, $at) {
    $slot = ReminderScheduler::initial($event(), 15, $at('2026-10-12 12:00'));
    Assert::same('2026-10-13 09:45', $slot['remindAt']->format('Y-m-d H:i'));
    Assert::same('2026-10-13 10:00', $slot['occurrence']->format('Y-m-d H:i'));
});

$t->test('pas de rappel programmé si son heure est déjà passée à la création', function () use ($event, $at) {
    Assert::same(null, ReminderScheduler::initial($event(), 15, $at('2026-10-13 09:50')));
});

$t->test('journée entière : référence à 9 h, « 1 jour avant » = la veille à 9 h', function () use ($event, $at) {
    $slot = ReminderScheduler::initial($event(['all_day' => 1, 'time' => '00:00']), 1440, $at('2026-10-01 08:00'));
    Assert::same('2026-10-12 09:00', $slot['remindAt']->format('Y-m-d H:i'));
});

$t->test('série hebdomadaire commencée : prochaine occurrence à venir', function () use ($event, $at) {
    $slot = ReminderScheduler::initial($event(['dateEvent' => '2026-09-01', 'recurrence' => 'weekly']), 60, $at('2026-10-12 12:00'));
    Assert::same('2026-10-13 09:00', $slot['remindAt']->format('Y-m-d H:i'));
});

$t->test('après envoi : occurrence suivante de la série', function () use ($event, $at) {
    $next = ReminderScheduler::next($event(['recurrence' => 'daily']), 15, '2026-10-13', $at('2026-10-13 09:46'));
    Assert::same('2026-10-14 10:00', $next['occurrence']->format('Y-m-d H:i'));
    Assert::same(null, ReminderScheduler::next($event(), 15, '2026-10-13', $at('2026-10-13 09:46')));
});

$t->test('reprise après un arrêt du cron : pas de rafale de rappels périmés', function () use ($event, $at) {
    // Rappel quotidien de 10 h resté bloqué sur le 10/10 ; il est maintenant 12 h le 13/10
    $next = ReminderScheduler::next($event(['dateEvent' => '2026-10-01', 'recurrence' => 'daily']), 15, '2026-10-10', $at('2026-10-13 12:00'));
    Assert::same('2026-10-14 10:00', $next['occurrence']->format('Y-m-d H:i'));
});

$t->test('expiration : 15 min après le début, ou fin de journée', function () use ($event) {
    Assert::same('2026-10-13 10:15', ReminderScheduler::expiry($event(), '2026-10-13')->format('Y-m-d H:i'));
    Assert::same('2026-10-14 00:00', ReminderScheduler::expiry($event(['all_day' => 1]), '2026-10-13')->format('Y-m-d H:i'));
});

$t->test('formulations relatives en français', function () use ($at) {
    $now = $at('2026-10-13 09:45');
    Assert::same('dans 15 minutes', DateText::relative($at('2026-10-13 10:00'), false, $now));
    Assert::same('dans 2 h 15', DateText::relative($at('2026-10-13 12:00'), false, $now));
    Assert::same('demain à 10:00', DateText::relative($at('2026-10-14 10:00'), false, $now));
    Assert::same('a commencé il y a 5 minutes', DateText::relative($at('2026-10-13 09:40'), false, $now));
    Assert::same("aujourd'hui (journée entière)", DateText::relative($at('2026-10-13 09:00'), true, $now));
    Assert::same('mardi 13 octobre 2026', DateText::longDate($at('2026-10-13')));
});
