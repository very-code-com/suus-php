<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Czech national public holidays (Státní svátky a ostatní svátky ČR).
 *
 * Fixed (11): Nový rok, Svátek práce, Den vítězství, Den Cyrila a Metoděje,
 *             Den upálení Jana Husa, Den české státnosti, Den vzniku ČSR,
 *             Den boje za svobodu, Štědrý den, 1. a 2. svátek vánoční.
 * Movable (2): Velký pátek, Velikonoční pondělí.
 */
final class CzechCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Nový rok / Den obnovy samostatného českého státu
        '05-01', // Svátek práce
        '05-08', // Den vítězství
        '07-05', // Den slovanských věrozvěstů Cyrila a Metoděje
        '07-06', // Den upálení mistra Jana Husa
        '09-28', // Den české státnosti
        '10-28', // Den vzniku samostatného československého státu
        '11-17', // Den boje za svobodu a demokracii
        '12-24', // Štědrý den
        '12-25', // 1. svátek vánoční
        '12-26', // 2. svátek vánoční
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->modify('-2 days')->format('Y-m-d'), // Velký pátek (Good Friday, since 2016)
            $easter->modify('+1 day')->format('Y-m-d'),  // Velikonoční pondělí
        ];
    }
}
