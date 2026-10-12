<?php
declare(strict_types=1);

/*
 * Lance la suite de tests :   php tests/run.php [filtre]
 *
 * Prérequis pour les tests d'intégration et HTTP : un serveur MySQL local
 * (ex. XAMPP) accessible avec l'utilisateur défini dans tests/config.testing.php.
 * La base « agendday_test » est recréée à chaque exécution.
 */

putenv('AGENDDAY_CONFIG=' . __DIR__ . '/config.testing.php');
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/lib/Tester.php';
require __DIR__ . '/lib/TestDatabase.php';
require __DIR__ . '/lib/HttpClient.php';

// Les échecs simulés (SMTP…) sont journalisés : on les écarte de la sortie console.
ini_set('error_log', sys_get_temp_dir() . '/agendday-tests.log');

$t = new Tester();

foreach (['unit', 'integration', 'http'] as $group) {
    foreach (glob(__DIR__ . "/{$group}/*Test.php") ?: [] as $file) {
        require $file;
    }
}

exit($t->run($argv[1] ?? null));
