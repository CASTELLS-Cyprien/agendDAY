<?php
declare(strict_types=1);

use App\Services\Recurrence;

/** @var Tester $t */
$t->suite('Récurrence (unitaire)');

$t->test('un événement simple n\'apparaît que dans la période qui le contient', function () {
    Assert::same(['2026-10-12'], Recurrence::between('2026-10-12', 'none', null, '2026-10-01', '2026-10-31'));
    Assert::same([], Recurrence::between('2026-10-12', 'none', null, '2026-11-01', '2026-11-30'));
});

$t->test('quotidien : une occurrence par jour, date de fin incluse', function () {
    Assert::same(
        ['2026-10-01', '2026-10-02', '2026-10-03'],
        Recurrence::between('2026-10-01', 'daily', '2026-10-03', '2026-09-01', '2026-10-31')
    );
});

$t->test('hebdomadaire : même jour de la semaine', function () {
    Assert::same(
        ['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'],
        Recurrence::between('2026-09-28', 'weekly', null, '2026-10-01', '2026-10-31')
    );
});

$t->test('mensuel le 31 : les mois sans 31 sont ignorés', function () {
    Assert::same(
        ['2026-01-31', '2026-03-31', '2026-05-31'],
        Recurrence::between('2026-01-31', 'monthly', null, '2026-01-01', '2026-06-30')
    );
});

$t->test('annuel le 29 février : uniquement les années bissextiles', function () {
    Assert::same(
        ['2024-02-29', '2028-02-29'],
        Recurrence::between('2024-02-29', 'yearly', null, '2024-01-01', '2029-12-31')
    );
});

$t->test('série ancienne : calcul direct sans parcourir des années d\'occurrences', function () {
    $dates = Recurrence::between('2000-01-01', 'daily', null, '2026-10-01', '2026-10-31');
    Assert::count(31, $dates);
    Assert::same('2026-10-01', $dates[0]);
    Assert::same('2026-10-31', $dates[30]);
});

$t->test('changement d\'heure (fin octobre) sans décalage de date', function () {
    Assert::same(
        ['2026-10-24', '2026-10-25', '2026-10-26'],
        Recurrence::between('2026-10-20', 'daily', null, '2026-10-24', '2026-10-26')
    );
});

$t->test('first() applique le filtre et respecte la date de fin', function () {
    Assert::same('2026-10-19', Recurrence::first('2026-10-05', 'weekly', null, '2026-10-06', fn ($d) => $d > '2026-10-12'));
    Assert::same(null, Recurrence::first('2026-10-05', 'weekly', '2026-10-12', '2026-10-13'));
});

$t->test('isOccurrence() reconnaît les dates de la série', function () {
    Assert::true(Recurrence::isOccurrence('2026-10-05', 'weekly', null, '2026-11-02'));
    Assert::false(Recurrence::isOccurrence('2026-10-05', 'weekly', null, '2026-11-03'));
    Assert::false(Recurrence::isOccurrence('2026-10-05', 'none', null, '2026-10-06'));
});
