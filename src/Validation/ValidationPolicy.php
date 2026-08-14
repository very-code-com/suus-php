<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Validation;

/**
 * Configurable strictness for the international-only validation rules.
 *
 * SUUS reserves several rules for international (non-domestic) orders: the order
 * must be B2B, B2C-only services (SMS pre-advice, inside delivery) are
 * unavailable, and returnable/stackable packaging is not allowed
 * (SUUS WebApi docs, WS PK 1.0). All of them are enforced by default, but which
 * routes count as "international" for a given merchant/contract can differ
 * (see {@see \VeryCodeCom\Suus\Routing\RouteClassifierInterface}). This policy
 * lets integrators relax the international-only enforcement in their own layer
 * instead of forking the library.
 *
 * Only the international-only, policy-shaped rules are toggleable here. Hard
 * protocol/format rules (incoterms required for international, freight+currency
 * pairing, weight/dimension/field-length limits, loading-date rules) are always
 * enforced because SUUS rejects them regardless.
 *
 * Immutable value object. Use {@see self::strict()} (the default) or
 * {@see self::relaxed()}, or construct with named arguments for fine control.
 *
 * @api
 */
final class ValidationPolicy
{
    public function __construct(
        /** Enforce "international orders must be orderType B2B" (SUUS PRJ00345). */
        public readonly bool $enforceInternationalB2B = true,
        /**
         * Enforce service/route availability in both directions:
         *   - domestic-only services (StdAwizacjaSms, StdWniesienie2,
         *     StdDokumentyZwrotneINiezwrotneGrid2) rejected on international orders;
         *   - international-only services (StdDokumentyZwrotneINiezwrotneGrid3)
         *     rejected on domestic orders.
         */
        public readonly bool $enforceServiceRouteRestrictions = true,
        /**
         * Enforce that returnable/stackable packaging is rejected on
         * international orders (SUUS PRJ00372 / PRJ00373).
         */
        public readonly bool $enforceInternationalPackagingRestrictions = true,
    ) {
    }

    /** The default: enforce every international-only rule (matches SUUS server behaviour). */
    public static function strict(): self
    {
        return new self();
    }

    /** Relax every route-shaped rule; SUUS still validates server-side. */
    public static function relaxed(): self
    {
        return new self(
            enforceInternationalB2B: false,
            enforceServiceRouteRestrictions: false,
            enforceInternationalPackagingRestrictions: false,
        );
    }
}
