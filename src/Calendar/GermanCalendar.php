<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * German federal public holidays (nationwide, all Bundesländer).
 *
 * Fixed (9): Neujahr, Tag der Arbeit, Tag der Deutschen Einheit,
 *            1. and 2. Weihnachtstag.
 * Movable (4): Karfreitag, Ostermontag, Christi Himmelfahrt, Pfingstmontag.
 *
 * Note: several Bundesländer observe additional regional holidays
 * (e.g. Epiphany in Bavaria, Corpus Christi in NRW). Those are not
 * included here - only the nine holidays valid in all 16 states.
 */
final class GermanCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Neujahr
        '05-01', // Tag der Arbeit
        '10-03', // Tag der Deutschen Einheit
        '12-25', // 1. Weihnachtstag
        '12-26', // 2. Weihnachtstag
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->modify('-2 days')->format('Y-m-d'),  // Karfreitag
            $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
            $easter->modify('+39 days')->format('Y-m-d'), // Christi Himmelfahrt
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
        ];
    }
}
