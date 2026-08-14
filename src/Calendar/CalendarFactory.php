<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Resolves the appropriate BusinessCalendarInterface implementation
 * for a given ISO 3166-1 alpha-2 country code.
 *
 * Used by SuusClient to auto-select the sender-country calendar when
 * no explicit calendar is injected via the constructor.
 */
final class CalendarFactory
{
    public static function forCountry(string $countryCode): BusinessCalendarInterface
    {
        return match (strtoupper($countryCode)) {
            'DE'    => new GermanCalendar(),
            'AT'    => new AustriaCalendar(),
            'CH'    => new SwitzerlandCalendar(),
            'CZ'    => new CzechCalendar(),
            'SK'    => new SlovakCalendar(),
            'HU'    => new HungarianCalendar(),
            'RO'    => new RomanianCalendar(),
            'SI'    => new SlovenianCalendar(),
            default => new PolishCalendar(), // PL and any unknown country -> Polish (SUUS HQ country)
        };
    }
}
