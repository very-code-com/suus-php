<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Contract for business-day calculations across different country calendars.
 * Implementations must account for weekends and country-specific public holidays.
 */
interface BusinessCalendarInterface
{
    public function isBusinessDay(\DateTimeImmutable $date): bool;

    /**
     * Add $days business days to $from, skipping weekends and public holidays.
     */
    public function addBusinessDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable;

    /**
     * Return the earliest valid loading date (today + $minAdvanceDays business days).
     */
    public function minLoadingDate(int $minAdvanceDays = 2, ?\DateTimeImmutable $referenceDate = null): \DateTimeImmutable;
}
