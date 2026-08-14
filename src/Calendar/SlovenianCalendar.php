<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Slovenian national public holidays (Državni prazniki in dela prosti dnevi RS).
 *
 * Fixed (12): Novo leto (2 dni), Prešernov dan, Dan upora, Praznik dela (2 dni),
 *             Dan državnosti, Marijino vnebovzetje, Dan reformacije,
 *             Dan spomina na mrtve, Božič, Dan samostojnosti in enotnosti.
 * Movable (2): Velika noč (Easter Sunday), Velikonočni ponedeljek (Easter Monday),
 *              Binkoštna nedelja (Whit Sunday).
 * Uses the Western (Gregorian) Easter calendar.
 */
final class SlovenianCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Novo leto (1. januar)
        '01-02', // Novo leto (2. januar)
        '02-08', // Prešernov dan
        '04-27', // Dan upora proti okupatorju
        '05-01', // Praznik dela (1. maj)
        '05-02', // Praznik dela (2. maj)
        '06-25', // Dan državnosti
        '08-15', // Marijino vnebovzetje
        '10-31', // Dan reformacije
        '11-01', // Dan spomina na mrtve
        '12-25', // Božič
        '12-26', // Dan samostojnosti in enotnosti
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->format('Y-m-d'),                     // Velika noč (Easter Sunday)
            $easter->modify('+1 day')->format('Y-m-d'),   // Velikonočni ponedeljek
            $easter->modify('+49 days')->format('Y-m-d'), // Binkoštna nedelja (Whit Sunday)
        ];
    }
}
