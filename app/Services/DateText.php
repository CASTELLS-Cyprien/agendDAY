<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;

/**
 * Formulations de dates en français, sans dépendre de l'extension intl
 * (pas toujours activée sur les hébergements mutualisés).
 */
final class DateText
{
    private const DAYS   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const MONTHS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    /** Ex. « lundi 12 octobre 2026 » */
    public static function longDate(DateTimeImmutable $date): string
    {
        return self::DAYS[(int) $date->format('w')] . ' ' . $date->format('j') . ' '
            . self::MONTHS[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    /** Ex. « dans 15 minutes », « dans 2 heures », « demain à 09:00 », « aujourd'hui ». */
    public static function relative(DateTimeImmutable $start, bool $allDay, DateTimeImmutable $now): string
    {
        $today = $now->format('Y-m-d');
        $day   = $start->format('Y-m-d');
        $dayDiff = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($day))->format('%r%a');

        if ($allDay) {
            return match (true) {
                $dayDiff === 0 => "aujourd'hui (journée entière)",
                $dayDiff === 1 => 'demain (journée entière)',
                $dayDiff > 1   => "dans {$dayDiff} jours (journée entière)",
                default        => 'le ' . self::longDate($start),
            };
        }

        $minutes = (int) floor(($start->getTimestamp() - $now->getTimestamp()) / 60);

        if ($minutes < 0) {
            $late = -$minutes;
            return 'a commencé il y a ' . $late . ' minute' . ($late > 1 ? 's' : '');
        }
        if ($minutes === 0) {
            return 'maintenant';
        }
        if ($minutes < 60) {
            return 'dans ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        if ($dayDiff === 0) {
            $hours = intdiv($minutes, 60);
            $rest  = $minutes % 60;
            return 'dans ' . $hours . ' h' . ($rest > 0 ? ' ' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT) : '');
        }
        if ($dayDiff === 1) {
            return 'demain à ' . $start->format('H:i');
        }
        return 'dans ' . $dayDiff . ' jours, le ' . self::longDate($start) . ' à ' . $start->format('H:i');
    }
}
