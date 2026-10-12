<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;

/**
 * Envoi d'un e-mail de rappel. Interface séparée pour pouvoir substituer un
 * faux expéditeur dans les tests du moteur de rappels.
 */
interface ReminderMailer
{
    /**
     * Doit lever une exception si le message n'a pas été accepté par le serveur d'envoi.
     *
     * @param array $event ligne `events` (id, title, time, end_time, all_day, location, descriptionEvent…)
     */
    public function sendReminder(
        string $email,
        string $userName,
        array $event,
        DateTimeImmutable $occurrence,
        string $relative
    ): void;
}
