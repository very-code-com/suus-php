<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Polish business day calendar.
 *
 * Fixed (9): Nowy Rok, Trzech Króli, Święto Pracy, Konstytucja 3 Maja,
 *            Wniebowzięcie NMP, Wszystkich Świętych, Narodowe Święto Niepodległości,
 *            Boże Narodzenie I, Boże Narodzenie II.
 * Movable (4): Wielkanoc, Poniedziałek Wielkanocny, Zielone Świątki, Boże Ciało.
 *
 * Used by SuusClient to validate and auto-compute loadingDate.
 * SUUS requires a minimum of +2 Polish business days advance notice.
 */
final class PolishCalendar extends AbstractCalendar implements PolishCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Nowy Rok
        '01-06', // Trzech Króli
        '05-01', // Święto Pracy
        '05-03', // Konstytucja 3 Maja
        '08-15', // Wniebowzięcie NMP
        '11-01', // Wszystkich Świętych
        '11-11', // Narodowe Święto Niepodległości
        '12-25', // Boże Narodzenie (I dzień)
        '12-26', // Boże Narodzenie (II dzień)
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->format('Y-m-d'),                                 // Wielkanoc
            $easter->modify('+1 day')->format('Y-m-d'),               // Poniedziałek Wielkanocny
            $easter->modify('+49 days')->format('Y-m-d'),             // Zielone Świątki
            $easter->modify('+60 days')->format('Y-m-d'),             // Boże Ciało
        ];
    }
}
