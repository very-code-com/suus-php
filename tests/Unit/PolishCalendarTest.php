<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Calendar\PolishCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Polish business day calculator.
 *
 * Covers: weekends, 9 fixed holidays, 4 Easter-based movable holidays,
 * addBusinessDays(), minLoadingDate() with the SUUS +2 day requirement.
 */
final class PolishCalendarTest extends TestCase
{
    private PolishCalendar $cal;

    protected function setUp(): void
    {
        $this->cal = new PolishCalendar();
    }

    // ──────────────────────────────────────────────
    // isBusinessDay - weekends
    // ──────────────────────────────────────────────

    public function testSaturdayIsNotABusinessDay(): void
    {
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-06-07'))); // Saturday
    }

    public function testSundayIsNotABusinessDay(): void
    {
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-06-08'))); // Sunday
    }

    public function testMondayIsABusinessDay(): void
    {
        // 2025-06-09 is a regular Monday (not a holiday)
        $this->assertTrue($this->cal->isBusinessDay(new \DateTimeImmutable('2025-06-09')));
    }

    // ──────────────────────────────────────────────
    // isBusinessDay - fixed holidays
    // ──────────────────────────────────────────────

    /** @dataProvider fixedHolidayProvider */
    #[DataProvider('fixedHolidayProvider')]
    public function testFixedHolidayIsNotABusinessDay(string $date): void
    {
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable($date)));
    }

    public static function fixedHolidayProvider(): array
    {
        return [
            'New Year 2025'           => ['2025-01-01'],
            'Three Kings 2025'        => ['2025-01-06'],
            'Labour Day 2025'         => ['2025-05-01'],
            'Constitution Day 2025'   => ['2025-05-03'],
            'Assumption Day 2025'     => ['2025-08-15'],
            'All Saints Day 2025'     => ['2025-11-01'],
            'Independence Day 2025'   => ['2025-11-11'],
            'Christmas Day 1 2025'    => ['2025-12-25'],
            'Christmas Day 2 2025'    => ['2025-12-26'],
        ];
    }

    // ──────────────────────────────────────────────
    // isBusinessDay - Easter-based movable holidays
    // ──────────────────────────────────────────────

    public function testEasterSundayIsNotABusinessDay(): void
    {
        // Easter 2025: April 20
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-04-20')));
    }

    public function testEasterMondayIsNotABusinessDay(): void
    {
        // Easter Monday 2025: April 21
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-04-21')));
    }

    public function testPentecostIsNotABusinessDay(): void
    {
        // Pentecost 2025 (Easter + 49 days): June 8 - also a Sunday
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-06-08')));
    }

    public function testCorpusChristiIsNotABusinessDay(): void
    {
        // Corpus Christi 2025 (Easter + 60 days): June 19
        $this->assertFalse($this->cal->isBusinessDay(new \DateTimeImmutable('2025-06-19')));
    }

    // ──────────────────────────────────────────────
    // easterDate()
    // ──────────────────────────────────────────────

    /** @dataProvider easterProvider */
    #[DataProvider('easterProvider')]
    public function testEasterDateCalculation(int $year, string $expected): void
    {
        $easter = $this->cal->easterDate($year);
        $this->assertSame($expected, $easter->format('Y-m-d'));
    }

    public static function easterProvider(): array
    {
        return [
            [2023, '2023-04-09'],
            [2024, '2024-03-31'],
            [2025, '2025-04-20'],
            [2026, '2026-04-05'],
            [2030, '2030-04-21'],
        ];
    }

    // ──────────────────────────────────────────────
    // addBusinessDays()
    // ──────────────────────────────────────────────

    public function testAddOneBusinessDaySkipsWeekend(): void
    {
        // Friday 2025-06-06 + 1 business day = Monday 2025-06-09
        $from   = new \DateTimeImmutable('2025-06-06');
        $result = $this->cal->addBusinessDays($from, 1);
        $this->assertSame('2025-06-09', $result->format('Y-m-d'));
    }

    public function testAddTwoBusinessDaysSkipsWeekendAndHoliday(): void
    {
        // Thursday 2025-04-17 + 2 business days:
        //   Fri Apr 18 = Good Friday (not a PL holiday, counted as business day)
        //   Skip Sat Apr 19, Sun Apr 20 (Easter), Mon Apr 21 (Easter Monday)
        //   → Tue Apr 22 (second business day)
        $from   = new \DateTimeImmutable('2025-04-17');
        $result = $this->cal->addBusinessDays($from, 2);
        $this->assertSame('2025-04-22', $result->format('Y-m-d'));
    }

    public function testAddZeroDays(): void
    {
        $from   = new \DateTimeImmutable('2025-06-09'); // Monday
        $result = $this->cal->addBusinessDays($from, 0);
        $this->assertSame('2025-06-09', $result->format('Y-m-d'));
    }

    // ──────────────────────────────────────────────
    // minLoadingDate() - SUUS +2 business days rule
    // ──────────────────────────────────────────────

    public function testMinLoadingDateFromMonday(): void
    {
        // Monday 2025-06-09: +2 business days = Wednesday 2025-06-11
        $min = $this->cal->minLoadingDate(2, new \DateTimeImmutable('2025-06-09'));
        $this->assertSame('2025-06-11', $min->format('Y-m-d'));
    }

    public function testMinLoadingDateFromThursdaySkipsWeekend(): void
    {
        // Thursday 2025-06-05:
        //   Day 1 = Fri Jun 06
        //   Day 2 = Mon Jun 09  (skip Sat, Sun)
        $min = $this->cal->minLoadingDate(2, new \DateTimeImmutable('2025-06-05'));
        $this->assertSame('2025-06-09', $min->format('Y-m-d'));
    }
}
