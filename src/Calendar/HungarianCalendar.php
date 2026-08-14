<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Hungarian national public holidays (Magyarország munkaszüneti napjai).
 *
 * Fixed (8): Újév, Nemzeti ünnep (márc. 15), Munka ünnepe, Államalapítás (aug. 20),
 *            Nemzeti ünnep (okt. 23), Mindenszentek, Karácsony I-II.
 * Movable (4): Húsvét vasárnapja, Húsvét hétfő, Pünkösd vasárnapja, Pünkösd hétfő.
 * Uses the Western (Gregorian) Easter calendar.
 */
final class HungarianCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Újév
        '03-15', // Nemzeti ünnep (1848-49-es forradalom)
        '05-01', // Munka ünnepe
        '08-20', // Az államalapítás ünnepe (István király napja)
        '10-23', // Nemzeti ünnep (1956-os forradalom)
        '11-01', // Mindenszentek
        '12-25', // Karácsony I. napja
        '12-26', // Karácsony II. napja
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->format('Y-m-d'),                                 // Húsvét vasárnapja
            $easter->modify('+1 day')->format('Y-m-d'),               // Húsvét hétfő
            $easter->modify('+49 days')->format('Y-m-d'),             // Pünkösd vasárnapja
            $easter->modify('+50 days')->format('Y-m-d'),             // Pünkösd hétfő
        ];
    }
}
