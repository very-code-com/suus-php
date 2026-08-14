<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

use VeryCodeCom\Suus\Enum\PackageSymbol;

/**
 * Immutable package value object.
 * Represents a single physical package (collo) to be shipped.
 *
 * SUUS physical limits (validated separately by ShipmentValidator):
 *   - max weight per package: 126 kg
 *   - max total weight per order: 800 kg
 *   - max packages per order: 124
 *
 * Note: SUUS uses the misspelled field name "lenghtCm" (not "lengthCm") in their SOAP API.
 *       The XML builder handles this quirk internally.
 *
 * Note: $returnable and $stackable are WSDL-defined optional integer fields.
 *       $stackable must be set to 1 when $symbol is EUR and $returnable > 0.
 */
final class Package
{
    public function __construct(
        /** SUUS package type symbol (KAR, EUR, JED, PLT, ...) */
        public readonly PackageSymbol $symbol,
        /** Gross weight in kilograms */
        public readonly float $weightKg,
        /** Length in centimetres (optional but recommended) */
        public readonly ?float $lengthCm = null,
        /** Width in centimetres (optional but recommended) */
        public readonly ?float $widthCm = null,
        /** Height in centimetres (optional but recommended) */
        public readonly ?float $heightCm = null,
        /** Number of returnable pallets/packages. Sent only when > 0. */
        public readonly ?int $returnable = null,
        /** Stackable flag (1 = stackable). Required when EUR + returnable > 0. */
        public readonly ?int $stackable = null,
    ) {
        if ($weightKg <= 0) {
            throw new \InvalidArgumentException("Package weightKg must be > 0, got: {$weightKg}.");
        }
    }
}
