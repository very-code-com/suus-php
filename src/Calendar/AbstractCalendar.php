<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Base implementation of BusinessCalendarInterface.
 * Subclasses provide fixed and movable holiday lists for their country.
 */
abstract class AbstractCalendar implements BusinessCalendarInterface
{
    /**
     * Fixed public holidays for this country as 'MM-DD' strings.
     *
     * @return string[]
     */
    abstract protected function getFixedHolidays(): array;

    /**
     * Movable (typically Easter-based) public holidays for the given year as 'YYYY-MM-DD' strings.
     *
     * @return string[]
     */
    abstract protected function getMovableHolidays(int $year): array;

    public function isBusinessDay(\DateTimeImmutable $date): bool
    {
        $dow = (int) $date->format('N');
        if ($dow >= 6) {
            return false;
        }

        if (in_array($date->format('m-d'), $this->getFixedHolidays(), true)) {
            return false;
        }

        return !in_array($date->format('Y-m-d'), $this->getMovableHolidays((int) $date->format('Y')), true);
    }

    public function addBusinessDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable
    {
        $current = $from;
        $added   = 0;

        while ($added < $days) {
            $current = $current->modify('+1 day');
            if ($this->isBusinessDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    public function minLoadingDate(int $minAdvanceDays = 2, ?\DateTimeImmutable $referenceDate = null): \DateTimeImmutable
    {
        $today = ($referenceDate ?? new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        return $this->addBusinessDays($today, $minAdvanceDays);
    }

    /**
     * Compute Orthodox Easter Sunday (Julian calendar converted to Gregorian).
     * Uses the Julian Meeus algorithm + 13-day correction valid for 1900-2099.
     * Required for Romania and other Orthodox-calendar countries.
     */
    public function orthodoxEasterDate(int $year): \DateTimeImmutable
    {
        $c = $year % 19;
        $d = (19 * $c + 15) % 30;
        $a = $year % 4;
        $b = $year % 7;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;

        $month = intdiv($d + $e + 114, 31);
        $day   = (($d + $e + 114) % 31) + 1;

        $julian = \DateTimeImmutable::createFromFormat('Y-n-j H:i:s', "{$year}-{$month}-{$day} 00:00:00");

        if ($julian === false) {
            throw new \LogicException("Failed to compute Orthodox Easter date for year {$year}.");
        }

        return $julian->modify('+13 days');
    }

    /**
     * Compute Easter Sunday for a given year using the Anonymous Gregorian algorithm.
     */
    public function easterDate(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        $dt = \DateTimeImmutable::createFromFormat('Y-n-j H:i:s', "{$year}-{$month}-{$day} 00:00:00");

        if ($dt === false) {
            throw new \LogicException("Failed to compute Easter date for year {$year}.");
        }

        return $dt;
    }
}
