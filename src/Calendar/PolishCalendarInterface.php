<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Calendar;

/**
 * Polish business day calendar contract.
 * A country-specific narrowing of BusinessCalendarInterface, for type hints that
 * need to demand the Polish calendar rather than any business calendar.
 */
interface PolishCalendarInterface extends BusinessCalendarInterface
{
}
