<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Slovak national public holidays (Štátne sviatky a dni pracovného pokoja SR).
 *
 * Fixed (13): Deň vzniku SR, Zjavenie Pána, Sviatok práce, Deň víťazstva,
 *             Sviatok Cyrila a Metoda, Výročie SNP, Deň Ústavy SR,
 *             Sedembolestná Panna Mária, Sviatok všetkých svätých,
 *             Deň boja za slobodu, Štedrý deň, 1. a 2. sviatok vianočný.
 * Movable (2): Veľký piatok, Veľkonočný pondelok.
 */
final class SlovakCalendar extends AbstractCalendar implements BusinessCalendarInterface
{
    private const FIXED_HOLIDAYS = [
        '01-01', // Deň vzniku Slovenskej republiky
        '01-06', // Zjavenie Pána
        '05-01', // Sviatok práce
        '05-08', // Deň víťazstva nad fašizmom
        '07-05', // Sviatok svätého Cyrila a svätého Metoda
        '08-29', // Výročie Slovenského národného povstania
        '09-01', // Deň Ústavy Slovenskej republiky
        '09-15', // Sedembolestná Panna Mária
        '11-01', // Sviatok všetkých svätých
        '11-17', // Deň boja za slobodu a demokraciu
        '12-24', // Štedrý deň
        '12-25', // 1. sviatok vianočný
        '12-26', // 2. sviatok vianočný
    ];

    protected function getFixedHolidays(): array
    {
        return self::FIXED_HOLIDAYS;
    }

    protected function getMovableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $easter->modify('-2 days')->format('Y-m-d'), // Veľký piatok (Good Friday)
            $easter->modify('+1 day')->format('Y-m-d'),  // Veľkonočný pondelok
        ];
    }
}
