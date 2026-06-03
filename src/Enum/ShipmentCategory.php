<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS shipment category.
 * DROBNICA is the standard freight category.
 */
enum ShipmentCategory: string
{
    case DROBNICA = 'DROBNICA';
    case PLUS24   = '24PLUS';
    case PTL      = 'PTL';
}
