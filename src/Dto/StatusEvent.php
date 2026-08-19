<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

/**
 * A single event in the SUUS shipment lifecycle, as returned by getEvents.
 */
final class StatusEvent
{
    public function __construct(
        /** Raw SUUS event code, e.g. "J_CR", "KOL", "UNDI". */
        public readonly string $rawCode,
        /** Human-readable description returned by SUUS. */
        public readonly string $description,
        /** Depot or city where the event occurred (may be empty). */
        public readonly string $location,
        /** When the event occurred; null if SUUS omitted the date or returned an invalid timestamp. */
        public readonly ?\DateTimeImmutable $occurredAt,
    ) {}
}
