<?php
declare(strict_types=1);

/** @var Tester $t */
$t->suite('Migration SQL sur une base historique (intégration MySQL)');

$t->test('la migration s\'applique et produit les colonnes attendues par le code', function () {
    if (!TestDatabase::available()) {
        throw new SkipTest('serveur MySQL local indisponible');
    }

    $legacy = TestDatabase::recreate('agendday_migration_test', [
        APP_ROOT . '/tests/fixtures/legacy_schema.sql',
        APP_ROOT . '/database/migrations/2026_10_12_events_reminders_notifications.sql',
    ]);
    $fresh = TestDatabase::recreate('agendday_schema_test', [APP_ROOT . '/database/schema.sql']);

    $columns = function (PDO $pdo, string $db): array {
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME, COLUMN_NAME'
        );
        $stmt->execute([':db' => $db]);
        return array_map(fn ($r) => $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'], $stmt->fetchAll());
    };

    Assert::same(
        $columns($fresh, 'agendday_schema_test'),
        $columns($legacy, 'agendday_migration_test'),
        'Base migrée et schéma neuf doivent avoir les mêmes colonnes'
    );

    // Données historiques conservées, valeurs par défaut appliquées
    Assert::same(3, (int) $legacy->query('SELECT COUNT(*) FROM events')->fetchColumn());
    Assert::same('general', $legacy->query('SELECT DISTINCT category FROM events')->fetchColumn());
    Assert::same(1, (int) $legacy->query('SELECT email_reminders FROM users')->fetchColumn());

    // Seul l'événement futur non encore rappelé reçoit un rappel « 5 min avant »
    $reminders = $legacy->query(
        'SELECT e.title, r.offset_minutes FROM event_reminders r JOIN events e ON e.id = r.event_id'
    )->fetchAll();
    Assert::same([['title' => 'Futur à rappeler', 'offset_minutes' => 5]], array_map(
        fn ($r) => ['title' => $r['title'], 'offset_minutes' => (int) $r['offset_minutes']],
        $reminders
    ));

    $legacy->exec('DROP DATABASE agendday_migration_test');
    $fresh->exec('DROP DATABASE agendday_schema_test');
});
