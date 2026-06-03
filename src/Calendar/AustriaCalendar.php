<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Austrian national public holidays (Gesetzliche Feiertage).
 *
 * Fixed (9): Neujahr, Heilige Drei Könige, Staatsfeiertag, Mariä Himmelfahrt,
 *            Nationalfeiertag, Allerheiligen, Mariä Empfängnis, Christtag, Stefanitag.
 * Movable (4): Ostermontag, Christi Himmelfahrt, Pfingstmontag, Fronleichnam.
 */
final class AustriaCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Neujahr
        '01-06', // Heilige Drei Könige
        '05-01', // Staatsfeiertag
        '08-15', // Mariä Himmelfahrt
        '10-26', // Nationalfeiertag
        '11-01', // Allerheiligen
        '12-08', // Mariä Empfängnis
        '12-25', // Christtag
        '12-26', // Stefanitag
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
            $easter->modify('+39 days')->format('Y-m-d'), // Christi Himmelfahrt
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
            $easter->modify('+60 days')->format('Y-m-d'), // Fronleichnam
        ];
    }
}
