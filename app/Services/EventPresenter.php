<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Conversion des lignes `events` en structures JSON pour le client.
 */
final class EventPresenter
{
    /**
     * Développe les événements en occurrences datées comprises dans [$from, $to].
     *
     * @param array<int, list<int>> $reminders délais par identifiant d'événement
     */
    public static function occurrences(array $rows, array $reminders, string $from, string $to): array
    {
        $items = [];
        foreach ($rows as $row) {
            $dates = Recurrence::between(
                (string) $row['dateEvent'],
                (string) $row['recurrence'],
                $row['recurrence_until'] ?: null,
                $from,
                $to
            );
            foreach ($dates as $date) {
                $items[] = self::event($row, $reminders[(int) $row['id']] ?? [], $date);
            }
        }

        // Tri chronologique : par jour, journées entières d'abord, puis par heure
        usort($items, static fn (array $a, array $b): int =>
            [$a['date'], $a['allDay'] ? 0 : 1, (string) $a['start'], $a['id']]
            <=> [$b['date'], $b['allDay'] ? 0 : 1, (string) $b['start'], $b['id']]);

        return $items;
    }

    public static function event(array $row, array $reminders, ?string $date = null): array
    {
        $allDay = (int) $row['all_day'] === 1;

        return [
            'id'              => (int) $row['id'],
            'date'            => $date ?? (string) $row['dateEvent'],
            'seriesStart'     => (string) $row['dateEvent'],
            'title'           => (string) $row['title'],
            'allDay'          => $allDay,
            'start'           => $allDay ? null : substr((string) $row['time'], 0, 5),
            'end'             => ($allDay || empty($row['end_time'])) ? null : substr((string) $row['end_time'], 0, 5),
            'location'        => (string) ($row['location'] ?? ''),
            'category'        => (string) $row['category'],
            'description'     => (string) ($row['descriptionEvent'] ?? ''),
            'recurrence'      => (string) $row['recurrence'],
            'recurrenceUntil' => $row['recurrence_until'] ?: null,
            'reminders'       => array_values(array_map('intval', $reminders)),
        ];
    }
}
