<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

/**
 * A SUUS delivery/pickup point returned by the getDeliveryPoints API method.
 *
 * @api
 */
final class DeliveryPoint
{
    public function __construct(
        public readonly string  $symbol,
        public readonly string  $name,
        public readonly string  $country,
        public readonly string  $postCode,
        public readonly string  $city,
        public readonly string  $street,
        public readonly string  $streetNo,
        /** Opening time in HH:MM format (e.g. "08:00"). Empty string if not provided. */
        public readonly string  $timeFrom = '',
        /** Closing time in HH:MM format (e.g. "17:00"). Empty string if not provided. */
        public readonly string  $timeTo   = '',
    ) {}
}
