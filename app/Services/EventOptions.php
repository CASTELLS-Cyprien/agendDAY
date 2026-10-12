<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Référentiel unique des valeurs autorisées pour un événement. Il est transmis
 * au JavaScript (cf. vue du calendrier) pour éviter toute divergence client/serveur.
 */
final class EventOptions
{
    public const CATEGORIES = [
        'general'   => 'Général',
        'work'      => 'Travail',
        'personal'  => 'Personnel',
        'study'     => 'Études',
        'health'    => 'Santé',
        'important' => 'Important',
    ];

    public const DEFAULT_CATEGORY = 'general';

    /** Délais de rappel proposés, en minutes avant le début de l'événement. */
    public const REMINDER_OFFSETS = [
        0     => "À l'heure de l'événement",
        5     => '5 minutes avant',
        15    => '15 minutes avant',
        30    => '30 minutes avant',
        60    => '1 heure avant',
        120   => '2 heures avant',
        1440  => '1 jour avant',
        10080 => '1 semaine avant',
    ];

    public const MAX_REMINDERS = 5;

    public const RECURRENCES = [
        'none'    => 'Ne se répète pas',
        'daily'   => 'Tous les jours',
        'weekly'  => 'Toutes les semaines',
        'monthly' => 'Tous les mois',
        'yearly'  => 'Tous les ans',
    ];

    /** Heure de référence des rappels d'un événement « journée entière ». */
    public const ALL_DAY_REMINDER_TIME = '09:00';

    public static function toClientConfig(): array
    {
        $offsets = [];
        foreach (self::REMINDER_OFFSETS as $minutes => $label) {
            $offsets[] = ['value' => $minutes, 'label' => $label];
        }

        return [
            'categories'         => self::CATEGORIES,
            'defaultCategory'    => self::DEFAULT_CATEGORY,
            'reminderOffsets'    => $offsets,
            'maxReminders'       => self::MAX_REMINDERS,
            'recurrences'        => self::RECURRENCES,
            'allDayReminderTime' => self::ALL_DAY_REMINDER_TIME,
        ];
    }
}
