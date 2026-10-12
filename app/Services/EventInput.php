<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Validation et normalisation des données d'un événement reçues du formulaire.
 * Les clés d'erreur correspondent aux noms des champs côté client.
 */
final class EventInput
{
    public const MAX_TITLE       = 200;
    public const MAX_LOCATION    = 200;
    public const MAX_DESCRIPTION = 2000;

    /**
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function fromRequest(array $input): array
    {
        $errors = [];

        $title = self::singleLine($input['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Indiquez un titre.';
        } elseif (mb_strlen($title) > self::MAX_TITLE) {
            $errors['title'] = 'Le titre ne doit pas dépasser ' . self::MAX_TITLE . ' caractères.';
        }

        $date = self::date($input['date'] ?? '');
        if ($date === null) {
            $errors['date'] = 'Indiquez une date valide.';
        }

        $allDay  = self::bool($input['allDay'] ?? '');
        $time    = null;
        $endTime = null;

        if (!$allDay) {
            $time = self::time($input['time'] ?? '');
            if ($time === null) {
                $errors['time'] = 'Indiquez une heure de début (HH:MM).';
            }

            $rawEnd = trim((string) ($input['endTime'] ?? ''));
            if ($rawEnd !== '') {
                $endTime = self::time($rawEnd);
                if ($endTime === null) {
                    $errors['endTime'] = 'Heure de fin invalide (HH:MM).';
                } elseif ($time !== null && $endTime <= $time) {
                    $errors['endTime'] = "L'heure de fin doit être postérieure à l'heure de début.";
                }
            }
        }

        $location = self::singleLine($input['location'] ?? '');
        if (mb_strlen($location) > self::MAX_LOCATION) {
            $errors['location'] = 'Le lieu ne doit pas dépasser ' . self::MAX_LOCATION . ' caractères.';
        }

        $category = (string) ($input['category'] ?? EventOptions::DEFAULT_CATEGORY);
        if ($category === '') {
            $category = EventOptions::DEFAULT_CATEGORY;
        }
        if (!array_key_exists($category, EventOptions::CATEGORIES)) {
            $errors['category'] = 'Catégorie inconnue.';
        }

        $description = self::multiLine($input['descriptionEvent'] ?? '');
        if (mb_strlen($description) > self::MAX_DESCRIPTION) {
            $errors['description'] = 'La description ne doit pas dépasser ' . self::MAX_DESCRIPTION . ' caractères.';
        }

        $recurrence = (string) ($input['recurrence'] ?? 'none');
        if ($recurrence === '') {
            $recurrence = 'none';
        }
        $recurrenceUntil = null;
        if (!in_array($recurrence, Recurrence::TYPES, true)) {
            $errors['recurrence'] = 'Type de répétition inconnu.';
        } elseif ($recurrence !== 'none') {
            $rawUntil = trim((string) ($input['recurrenceUntil'] ?? ''));
            if ($rawUntil !== '') {
                $recurrenceUntil = self::date($rawUntil);
                if ($recurrenceUntil === null) {
                    $errors['recurrenceUntil'] = 'Date de fin de répétition invalide.';
                } elseif ($date !== null && $recurrenceUntil <= $date) {
                    $errors['recurrenceUntil'] = "La fin de la répétition doit être postérieure à la date de l'événement.";
                }
            }
        }

        $reminders    = [];
        $rawReminders = $input['reminders'] ?? [];
        if (!is_array($rawReminders)) {
            $rawReminders = [$rawReminders];
        }
        foreach ($rawReminders as $raw) {
            if (!is_scalar($raw) || !preg_match('/^\d{1,5}$/', (string) $raw)
                || !array_key_exists((int) $raw, EventOptions::REMINDER_OFFSETS)) {
                $errors['reminders'] = 'Délai de rappel invalide.';
                break;
            }
            $reminders[(int) $raw] = (int) $raw;
        }
        if (count($reminders) > EventOptions::MAX_REMINDERS) {
            $errors['reminders'] = 'Vous pouvez programmer ' . EventOptions::MAX_REMINDERS . ' rappels au maximum.';
        }
        sort($reminders);

        return [
            'data' => [
                'title'            => $title,
                'date'             => $date,
                'all_day'          => $allDay,
                'time'             => $allDay ? '00:00' : $time,
                'end_time'         => $endTime,
                'location'         => $location === '' ? null : $location,
                'category'         => $category,
                'description'      => $description,
                'recurrence'       => $recurrence,
                'recurrence_until' => $recurrenceUntil,
                'reminders'        => array_values($reminders),
            ],
            'errors' => $errors,
        ];
    }

    private static function singleLine(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text));
    }

    private static function multiLine(mixed $value): string
    {
        $text = is_scalar($value) ? str_replace("\r\n", "\n", (string) $value) : '';
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));
    }

    private static function bool(mixed $value): bool
    {
        return in_array($value, ['1', 'on', 'true', 1, true], true);
    }

    private static function date(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }
        $year = (int) $m[1];
        if ($year < 1970 || $year > 2100 || !checkdate((int) $m[2], (int) $m[3], $year)) {
            return null;
        }
        return $value;
    }

    private static function time(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value, $m)) {
            return null;
        }
        return $m[1] . ':' . $m[2];
    }
}
