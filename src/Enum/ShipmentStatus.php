<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * Normalized shipment status, mapped from SUUS native event codes.
 *
 * Native code → ShipmentStatus mapping:
 *   J_CR, KOL, M_KOL        → Created
 *   LOAD, ZALF, ZAL, M_DYS, WTRF → InTransit
 *   ROZF, UNDI, UNLO         → Delivered
 *   ANUL                     → Cancelled
 *   ZWRON, ZTF               → Failed
 */
enum ShipmentStatus: string
{
    case Pending   = 'pending';
    case Created   = 'created';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed    = 'failed';
}
