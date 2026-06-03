<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

/**
 * Result of a successful addOrder (createShipment) call.
 */
final class ShipmentResult
{
    public function __construct(
        /** SUUS Job number, e.g. "OPLKRI2600895". Use this for status polling and label retrieval. */
        public readonly string $shipmentNo,
        /** The reference you sent in the request (header/reference). */
        public readonly string $reference,
        /** Pre-built tracking URL pointing to the SUUS order portal. */
        public readonly string $trackingUrl,
    ) {}
}
