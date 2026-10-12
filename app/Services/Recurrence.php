<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Calcul des occurrences d'un événement récurrent, sur des dates au format Y-m-d.
 *
 * Règles :
 *  - mensuel le 31 : les mois sans 31 sont ignorés (comme la plupart des agendas) ;
 *  - annuel le 29 février : uniquement les années bissextiles ;
 *  - $until (facultatif) est la dernière date possible, incluse.
 *
 * Les calculs se font en UTC pour ne pas être perturbés par les changements d'heure.
 */
final class Recurrence
{
    public const TYPES = ['none', 'daily', 'weekly', 'monthly', 'yearly'];

    private const MAX_ITERATIONS = 5000;

    /**
     * Occurrences comprises entre $from et $to inclus.
     *
     * @return list<string>
     */
    public static function between(string $start, string $type, ?string $until, string $from, string $to): array
    {
        $end = ($until !== null && $until < $to) ? $until : $to;

        if ($type === 'none') {
            return ($start >= $from && $start <= $end) ? [$start] : [];
        }

        $startDate = self::date($start);
        $dates     = [];
        $k         = self::firstIndexNear($startDate, $type, $from);

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++, $k++) {
            $date = self::nth($startDate, $type, $k);
            if ($date === null) {
                continue;
            }
            if ($date > $end) {
                break;
            }
            if ($date >= $from) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * Première occurrence à partir de $from (incluse) qui satisfait $accept.
     */
    public static function first(
        string $start,
        string $type,
        ?string $until,
        string $from,
        ?callable $accept = null
    ): ?string {
        if ($type === 'none') {
            return ($start >= $from && ($accept === null || $accept($start))) ? $start : null;
        }

        $startDate = self::date($start);
        $k         = self::firstIndexNear($startDate, $type, $from);

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++, $k++) {
            $date = self::nth($startDate, $type, $k);
            if ($date === null || $date < $from) {
                continue;
            }
            if ($until !== null && $date > $until) {
                return null;
            }
            if ($accept === null || $accept($date)) {
                return $date;
            }
        }

        return null;
    }

    public static function isOccurrence(string $start, string $type, ?string $until, string $date): bool
    {
        return self::first($start, $type, $until, $date) === $date;
    }

    /**
     * Date de la k-ième occurrence (k = 0 : date de départ), ou null si elle n'existe pas.
     */
    private static function nth(DateTimeImmutable $start, string $type, int $k): ?string
    {
        if ($k === 0) {
            return $start->format('Y-m-d');
        }

        $year  = (int) $start->format('Y');
        $month = (int) $start->format('n');
        $day   = (int) $start->format('j');

        switch ($type) {
            case 'daily':
                return $start->modify("+{$k} days")->format('Y-m-d');
            case 'weekly':
                return $start->modify('+' . (7 * $k) . ' days')->format('Y-m-d');
            case 'monthly':
                $monthIndex = $month - 1 + $k;
                $year      += intdiv($monthIndex, 12);
                $month      = $monthIndex % 12 + 1;
                break;
            case 'yearly':
                $year += $k;
                break;
            default:
                return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /**
     * Indice de départ de l'itération : jamais supérieur à celui de la première
     * occurrence >= $from, pour ne pas en manquer.
     */
    private static function firstIndexNear(DateTimeImmutable $start, string $type, string $from): int
    {
        $fromDate = self::date($from);
        if ($fromDate <= $start) {
            return 0;
        }

        $days   = (int) $start->diff($fromDate)->days;
        $months = ((int) $fromDate->format('Y') - (int) $start->format('Y')) * 12
            + (int) $fromDate->format('n') - (int) $start->format('n');

        return match ($type) {
            'daily'   => $days,
            'weekly'  => intdiv($days, 7),
            'monthly' => max(0, $months - 1),
            'yearly'  => max(0, intdiv($months, 12) - 1),
            default   => 0,
        };
    }

    private static function date(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd, new DateTimeZone('UTC'));
    }
}
