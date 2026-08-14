<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Romanian national public holidays (Sărbători legale în România).
 *
 * Fixed (10): Anul Nou (2 zile), Ziua Unirii, Ziua Muncii, Ziua Copilului,
 *             Adormirea Maicii Domnului, Sfântul Andrei, Ziua Națională,
 *             Crăciun (2 zile).
 * Movable (5): Uses the ORTHODOX Easter calendar (Julian + 13-day Gregorian correction),
 *              which can differ from Western Easter by up to 5 weeks.
 *              Vinerea Mare (Good Friday, since 2024), Paști (Easter Sun & Mon),
 *              Rusalii (Whit Sun & Mon).
 */
final class RomanianCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Anul Nou
        '01-02', // A doua zi de Anul Nou
        '01-24', // Ziua Unirii Principatelor Române
        '05-01', // Ziua Muncii
        '06-01', // Ziua Copilului
        '08-15', // Adormirea Maicii Domnului
        '11-30', // Sfântul Apostol Andrei
        '12-01', // Ziua Națională a României
        '12-25', // Crăciun
        '12-26', // A doua zi de Crăciun
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->orthodoxEasterDate($year);

        return [
            $easter->modify('-2 days')->format('Y-m-d'), // Vinerea Mare (Good Friday, since 2024)
            $easter->format('Y-m-d'),                    // Paști (Easter Sunday)
            $easter->modify('+1 day')->format('Y-m-d'),  // A doua zi de Paști (Easter Monday)
            $easter->modify('+49 days')->format('Y-m-d'), // Rusalii (Whit Sunday)
            $easter->modify('+50 days')->format('Y-m-d'), // A doua zi de Rusalii (Whit Monday)
        ];
    }
}
