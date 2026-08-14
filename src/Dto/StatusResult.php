<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

use VeryCodeCom\Suus\Enum\ShipmentStatus;

/**
 * Result of a getEvents (fetchStatus) call.
 */
final class StatusResult
{
    /**
     * @param StatusEvent[] $events  All events in the order they were returned by SUUS.
     */
    public function __construct(
        /** Normalized status derived from the most recent recognized SUUS event code. */
        public readonly ShipmentStatus $status,
        /** Raw SUUS event code of the most recent event (e.g. "UNDI"). Empty if no events. */
        public readonly string $rawLatestCode,
        public readonly array $events,
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === ShipmentStatus::Delivered;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            ShipmentStatus::Delivered,
            ShipmentStatus::Cancelled,
            ShipmentStatus::Failed,
        ], true);
    }
}
