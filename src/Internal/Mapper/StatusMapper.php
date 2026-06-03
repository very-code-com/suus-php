<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Mapper;

use VeryCodeCom\Suus\Enum\ShipmentStatus;

/**
 * Maps native SUUS event codes to normalized ShipmentStatus enum values.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class StatusMapper
{
    private const MAP = [
        'J_CR'  => ShipmentStatus::Created,
        'KOL'   => ShipmentStatus::Created,
        'M_KOL' => ShipmentStatus::Created,
        'LOAD'  => ShipmentStatus::InTransit,
        'ZALF'  => ShipmentStatus::InTransit,
        'ZAL'   => ShipmentStatus::InTransit,
        'M_DYS' => ShipmentStatus::InTransit,
        'WTRF'  => ShipmentStatus::InTransit,
        'ROZF'  => ShipmentStatus::Delivered,
        'UNDI'  => ShipmentStatus::Delivered,
        'UNLO'  => ShipmentStatus::Delivered,
        'ANUL'  => ShipmentStatus::Cancelled,
        'ZWRON' => ShipmentStatus::Failed,
        'ZTF'   => ShipmentStatus::Failed,
    ];

    /**
     * Map a single SUUS event code to a ShipmentStatus.
     * Returns ShipmentStatus::Created for unknown codes (safe default).
     */
    public function map(string $code): ShipmentStatus
    {
        return self::MAP[$code] ?? ShipmentStatus::Created;
    }

    /**
     * Given a list of raw event rows (from ResponseParser::events()),
     * return the "most advanced" status seen across all events.
     * Terminal statuses (Delivered, Cancelled, Failed) always win.
     *
     * @param array<array{code: string, ...}> $events
     */
    public function resolveFromEvents(array $events): ShipmentStatus
    {
        $current = ShipmentStatus::Pending;

        foreach ($events as $event) {
            $code = $event['code'];
            if (!isset(self::MAP[$code])) {
                continue;
            }
            $mapped = self::MAP[$code];

            // Advance status if the new one is "later" in the lifecycle
            if ($this->priority($mapped) > $this->priority($current)) {
                $current = $mapped;
            }
        }

        return $current;
    }

    private function priority(ShipmentStatus $status): int
    {
        return match ($status) {
            ShipmentStatus::Pending   => 0,
            ShipmentStatus::Created   => 1,
            ShipmentStatus::InTransit => 2,
            ShipmentStatus::Delivered => 3,
            ShipmentStatus::Cancelled => 3,
            ShipmentStatus::Failed    => 3,
        };
    }

    /**
     * Check whether a SUUS code is a known/recognized code.
     */
    public function isKnown(string $code): bool
    {
        return isset(self::MAP[$code]);
    }
}
