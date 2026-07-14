<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS order types.
 * B2B is required for international (non-PL->PL) routes.
 */
enum OrderType: string
{
    case B2B = 'B2B';
    case B2C = 'B2C';
}
