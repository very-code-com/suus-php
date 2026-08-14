<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Swiss public holidays - conservative set observed in the majority of cantons.
 *
 * Switzerland has no single list of national holidays (only 1 August is
 * constitutionally guaranteed). This implementation includes the four fixed days
 * observed by at least 22 of 26 cantons, plus the four movable Easter-based days
 * observed by most cantons.
 *
 * Fixed (4): Neujahr, Bundesfeiertag, Weihnachten, Stephanstag.
 * Movable (4): Karfreitag, Ostermontag, Auffahrt, Pfingstmontag.
 *
 * Cantonal-only holidays (e.g. Berchtoldstag in most German-speaking cantons,
 * Corpus Christi in Catholic cantons) are not included.
 */
final class SwitzerlandCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Neujahr
        '08-01', // Bundesfeiertag
        '12-25', // Weihnachten
        '12-26', // Stephanstag
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
            $easter->modify('+39 days')->format('Y-m-d'), // Auffahrt (Ascension)
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
        ];
    }
}
