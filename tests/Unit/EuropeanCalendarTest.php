<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Calendar\AustriaCalendar;
use VeryCodeCom\Suus\Calendar\CzechCalendar;
use VeryCodeCom\Suus\Calendar\GermanCalendar;
use VeryCodeCom\Suus\Calendar\HungarianCalendar;
use VeryCodeCom\Suus\Calendar\RomanianCalendar;
use VeryCodeCom\Suus\Calendar\SlovakCalendar;
use VeryCodeCom\Suus\Calendar\SlovenianCalendar;
use VeryCodeCom\Suus\Calendar\SwitzerlandCalendar;

/**
 * Tests for DE, AT, CH, CZ, SK country calendars.
 *
 * Key dates used (2025 Easter = 2025-04-20):
 *  Karfreitag / Good Friday  = 2025-04-18
 *  Ostermontag / Easter Mon  = 2025-04-21
 *  Christi Himmelfahrt / Ascension = 2025-05-29
 *  Pfingstmontag / Whit Mon  = 2025-06-09
 *  Fronleichnam / Corpus Chr = 2025-06-19
 */
final class EuropeanCalendarTest extends TestCase
{
    // --------------------------- GermanCalendar ---------------------------

    public function testGermanNewYearIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-01-01')));
    }

    public function testGermanLabourDayIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-01')));
    }

    public function testGermanUnityDayIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-10-03')));
    }

    public function testGermanChristmasIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-25')));
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-26')));
    }

    public function testGermanGoodFridayIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-18')));
    }

    public function testGermanEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testGermanAscensionIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-29')));
    }

    public function testGermanWhitMondayIsHoliday(): void
    {
        $this->assertFalse((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-09')));
    }

    public function testGermanCorpusChristiIsBusinessDay(): void
    {
        // Corpus Christi is NOT a federal holiday in Germany
        $this->assertTrue((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-19')));
    }

    public function testGermanPolishIndependenceDayIsBusinessDay(): void
    {
        // 11 Nov is Poland's Independence Day but not a German holiday; 2025-11-11 is a Tuesday
        $this->assertTrue((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-11-11')));
    }

    public function testGermanRegularMondayIsBusinessDay(): void
    {
        $this->assertTrue((new GermanCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-02')));
    }

    public function testGermanAddBusinessDaysSkipsGoodFriday(): void
    {
        // Thursday 2025-04-17 + 2 German business days:
        //   Fri 04-18 = Good Friday (DE holiday, skip)
        //   Sat 04-19 = weekend (skip)
        //   Sun 04-20 = Easter (skip, also weekend)
        //   Mon 04-21 = Easter Monday (DE holiday, skip)
        //   Tue 04-22 = day 1
        //   Wed 04-23 = day 2
        $result = (new GermanCalendar())->addBusinessDays(new DateTimeImmutable('2025-04-17'), 2);
        $this->assertSame('2025-04-23', $result->format('Y-m-d'));
    }

    // --------------------------- AustriaCalendar --------------------------

    public function testAustrianNationalDayIsHoliday(): void
    {
        $this->assertFalse((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-10-26')));
    }

    public function testAustrianAssumptionIsHoliday(): void
    {
        $this->assertFalse((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-08-15')));
    }

    public function testAustrianImmaculateConceptionIsHoliday(): void
    {
        $this->assertFalse((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-08')));
    }

    public function testAustrianCorpusChristiIsHoliday(): void
    {
        $this->assertFalse((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-19')));
    }

    public function testAustrianGoodFridayIsBusinessDay(): void
    {
        // Good Friday is NOT a public holiday in Austria
        $this->assertTrue((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-18')));
    }

    public function testAustrianEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new AustriaCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    // --------------------------- SwitzerlandCalendar ---------------------

    public function testSwissNationalDayIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-08-01')));
    }

    public function testSwissChristmasIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-25')));
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-26')));
    }

    public function testSwissGoodFridayIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-18')));
    }

    public function testSwissEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testSwissAscensionIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-29')));
    }

    public function testSwissWhitMondayIsHoliday(): void
    {
        $this->assertFalse((new SwitzerlandCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-09')));
    }

    // --------------------------- CzechCalendar ----------------------------

    public function testCzechGoodFridayIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-18')));
    }

    public function testCzechEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testCzechVictoryDayIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-08')));
    }

    public function testCzechStatehoodDayIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-09-28')));
    }

    public function testCzechChristmasEveIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-24')));
    }

    public function testCzechFreedomDayIsHoliday(): void
    {
        $this->assertFalse((new CzechCalendar())->isBusinessDay(new DateTimeImmutable('2025-11-17')));
    }

    // --------------------------- SlovakCalendar ---------------------------

    public function testSlovakEpiphanyIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-01-06')));
    }

    public function testSlovakGoodFridayIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-18')));
    }

    public function testSlovakEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testSlovakSNPAnniversaryIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-08-29')));
    }

    public function testSlovakConstitutionDayIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-09-01')));
    }

    public function testSlovakOurLadyOfSorrowsIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-09-15')));
    }

    public function testSlovakChristmasEveIsHoliday(): void
    {
        $this->assertFalse((new SlovakCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-24')));
    }

    // --------------------------- HungarianCalendar -----------------------

    public function testHungarianNationalDayMarch15IsHoliday(): void
    {
        // 2024-03-15 is a Friday - Hungarian national holiday (1848 revolution)
        $this->assertFalse((new HungarianCalendar())->isBusinessDay(new DateTimeImmutable('2024-03-15')));
    }

    public function testHungarianFoundationDayIsHoliday(): void
    {
        // 2025-08-20 is a Wednesday - St. Stephen's Day / Foundation of the State
        $this->assertFalse((new HungarianCalendar())->isBusinessDay(new DateTimeImmutable('2025-08-20')));
    }

    public function testHungarianRevolutionDayOct23IsHoliday(): void
    {
        // 2025-10-23 is a Thursday
        $this->assertFalse((new HungarianCalendar())->isBusinessDay(new DateTimeImmutable('2025-10-23')));
    }

    public function testHungarianEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new HungarianCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testHungarianWhitMondayIsHoliday(): void
    {
        $this->assertFalse((new HungarianCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-09')));
    }

    // --------------------------- RomanianCalendar ------------------------

    public function testRomanianUsesOrthodoxEaster(): void
    {
        // In 2024: Western Easter = March 31, Orthodox Easter = May 5
        // Romanian Easter Monday = May 6, 2024 (Monday)
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2024-05-06')));
        // Western Easter Monday (April 1) is NOT a holiday in Romania
        $this->assertTrue((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2024-04-01')));
    }

    public function testRomanianOrthodoxGoodFridayIsHoliday(): void
    {
        // Orthodox Good Friday 2024 = May 3, 2024 (Friday)
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2024-05-03')));
    }

    public function testRomanianOrthodoxWhitMondayIsHoliday(): void
    {
        // Orthodox Whit Monday 2024 = June 24, 2024 (Monday)
        // Orthodox Easter 2024 = May 5, +50 days = June 24
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2024-06-24')));
    }

    public function testRomanianNationalDayIsHoliday(): void
    {
        // 2025-12-01 is a Monday
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2025-12-01')));
    }

    public function testRomanianUnificationDayIsHoliday(): void
    {
        // 2025-01-24 is a Friday
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2025-01-24')));
    }

    public function testRomanianStAndrewsDayIsHoliday(): void
    {
        // 2025-11-30 is a Sunday - but let's use 2026-11-30 (Monday)
        $this->assertFalse((new RomanianCalendar())->isBusinessDay(new DateTimeImmutable('2026-11-30')));
    }

    // --------------------------- SlovenianCalendar -----------------------

    public function testSlovenianPreserenDayIsHoliday(): void
    {
        // 2024-02-08 is a Thursday - Prešeren Day
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2024-02-08')));
    }

    public function testSlovenianUpraisingDayIsHoliday(): void
    {
        // 2025-04-27 is a Sunday - but 2026-04-27 is a Monday
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2026-04-27')));
    }

    public function testSlovenianLabourDayBothDaysAreHolidays(): void
    {
        // 2025-05-01 = Thursday, 2025-05-02 = Friday
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-01')));
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-05-02')));
    }

    public function testSlovenianStatehoodDayIsHoliday(): void
    {
        // 2025-06-25 is a Wednesday
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-25')));
    }

    public function testSlovenianReformationDayIsHoliday(): void
    {
        // 2025-10-31 is a Friday
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-10-31')));
    }

    public function testSlovenianEasterMondayIsHoliday(): void
    {
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-04-21')));
    }

    public function testSlovenianWhitSundayIsNotBusinessDay(): void
    {
        // Whit Sunday 2025 = June 8 (always Sunday in 2025)
        $this->assertFalse((new SlovenianCalendar())->isBusinessDay(new DateTimeImmutable('2025-06-08')));
    }

    // --------------------------- CalendarFactory -------------------------

    public function testCalendarFactoryReturnsCorrectCalendars(): void
    {
        $cases = [
            'PL' => \VeryCodeCom\Suus\Calendar\PolishCalendar::class,
            'DE' => GermanCalendar::class,
            'AT' => AustriaCalendar::class,
            'CH' => SwitzerlandCalendar::class,
            'CZ' => CzechCalendar::class,
            'SK' => SlovakCalendar::class,
            'HU' => HungarianCalendar::class,
            'RO' => RomanianCalendar::class,
            'SI' => SlovenianCalendar::class,
        ];

        foreach ($cases as $code => $expectedClass) {
            $cal = \VeryCodeCom\Suus\Calendar\CalendarFactory::forCountry($code);
            $this->assertInstanceOf($expectedClass, $cal, "CalendarFactory::forCountry('{$code}')");
        }
    }

    public function testCalendarFactoryFallsBackToPolishForUnknownCountry(): void
    {
        $cal = \VeryCodeCom\Suus\Calendar\CalendarFactory::forCountry('XX');
        $this->assertInstanceOf(\VeryCodeCom\Suus\Calendar\PolishCalendar::class, $cal);
    }

    // --------------------------- Cross-country ---------------------------

    /** @dataProvider countriesProvider */
    #[DataProvider('countriesProvider')]
    public function testAllCountriesTreatWeekendAsNonBusinessDay(string $countryClass): void
    {
        $cal = new $countryClass();
        $this->assertFalse($cal->isBusinessDay(new DateTimeImmutable('2025-06-07'))); // Saturday
        $this->assertFalse($cal->isBusinessDay(new DateTimeImmutable('2025-06-08'))); // Sunday
    }

    /** @dataProvider countriesProvider */
    #[DataProvider('countriesProvider')]
    public function testAllCountriesTreatNewYearAsHoliday(string $countryClass): void
    {
        $cal = new $countryClass();
        $this->assertFalse($cal->isBusinessDay(new DateTimeImmutable('2025-01-01')));
    }

    /** @dataProvider countriesProvider */
    #[DataProvider('countriesProvider')]
    public function testAllCountriesTreatRegularMondayAsBusinessDay(string $countryClass): void
    {
        $cal = new $countryClass();
        // 2025-06-02 is a regular Monday with no holidays in any of these countries
        $this->assertTrue($cal->isBusinessDay(new DateTimeImmutable('2025-06-02')));
    }

    public static function countriesProvider(): array
    {
        return [
            'Germany'     => [GermanCalendar::class],
            'Austria'     => [AustriaCalendar::class],
            'Switzerland' => [SwitzerlandCalendar::class],
            'Czech'       => [CzechCalendar::class],
            'Slovakia'    => [SlovakCalendar::class],
            'Hungary'     => [HungarianCalendar::class],
            'Romania'     => [RomanianCalendar::class],
            'Slovenia'    => [SlovenianCalendar::class],
        ];
    }
}
