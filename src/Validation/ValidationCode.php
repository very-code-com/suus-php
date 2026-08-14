<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Validation;

/**
 * Stable identifiers for each local validation rule, attached to
 * {@see ValidationError::$code} so integrators can map failures to their own
 * localised UI and decide per-rule how strict to be.
 *
 * Where SUUS defines an equivalent server-side code (SUUS WebApi docs,
 * WS PK 1.0, error-code table p. 22-23), we reuse that exact code so a locally
 * rejected order carries the same identifier SUUS would have returned. Rules
 * with no exact SUUS equivalent use the library-specific "SUUSPHP_" prefix.
 *
 * @api
 */
final class ValidationCode
{
    // --- Packages: weight / count / dimensions (SUUS codes) ---------------
    public const PACKAGE_WEIGHT_EXCEEDED = 'PRJ00351'; // per-package > 126 kg
    public const TOTAL_WEIGHT_EXCEEDED   = 'PRJ00352'; // order total > 800 kg
    public const PACKAGE_COUNT_EXCEEDED  = 'PRJ00296'; // > 124 packages
    public const DIMENSION_EXCEEDED      = 'PRJ00308'; // length/width/height over max

    // --- Packages: EUR minimum height (no official SUUS code) -------------
    public const EUR_MIN_HEIGHT = 'SUUSPHP_EUR_MIN_HEIGHT';

    // --- Loading date (SUUS codes) ----------------------------------------
    public const LOADING_NOT_BUSINESS_DAY = 'DRG00073'; // weekend
    public const LOADING_ON_HOLIDAY       = 'DRG00079'; // public holiday
    public const LOADING_TOO_SOON         = 'DRG00091'; // below the +2 business-day minimum

    // --- International header rules (SUUS codes) ---------------------------
    public const INCOTERMS_REQUIRED     = 'PRJ00313'; // missing/incorrect incoterms on international order
    public const INTERNATIONAL_B2B_ONLY = 'PRJ00345'; // international orders must be orderType B2B
    public const FREIGHT_CURRENCY_PAIR  = 'PRJ00387'; // freight + currency both-or-neither

    // --- International packaging restrictions (SUUS codes) -----------------
    public const RETURNABLE_INTERNATIONAL = 'PRJ00372'; // returnable packaging not available internationally
    public const STACKABLE_INTERNATIONAL  = 'PRJ00373'; // stacking not available internationally

    /**
     * A domestic-only service (e.g. StdAwizacjaSms, StdWniesienie2,
     * StdDokumentyZwrotneINiezwrotneGrid2) used on an international order. SUUS
     * has no single dedicated code for this; the server rejects it generically
     * via the additional-services validation (DRG00152).
     */
    public const DOMESTIC_ONLY_SERVICE = 'SUUSPHP_DOMESTIC_ONLY_SERVICE';

    /**
     * An international-only service (e.g. StdDokumentyZwrotneINiezwrotneGrid3)
     * used on a domestic order. Rejected server-side via the additional-services
     * validation (DRG00152).
     */
    public const INTERNATIONAL_ONLY_SERVICE = 'SUUSPHP_INTERNATIONAL_ONLY_SERVICE';

    // --- Field format / length (no dedicated SUUS code -> PRJ00324) -------
    public const FIELD_TOO_LONG      = 'PRJ00324'; // any field over its WSDL length limit
    public const CURRENCY_LENGTH     = 'SUUSPHP_CURRENCY_LENGTH'; // currency must be exactly 3 chars

    private function __construct()
    {
    }
}
