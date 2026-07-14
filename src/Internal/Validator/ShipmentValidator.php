<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Validator;

use VeryCodeCom\Suus\Calendar\BusinessCalendarInterface;
use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Routing\DefaultRouteClassifier;
use VeryCodeCom\Suus\Routing\RouteClassifierInterface;
use VeryCodeCom\Suus\Service\ServiceInterface;
use VeryCodeCom\Suus\Validation\ValidationCode;
use VeryCodeCom\Suus\Validation\ValidationError;
use VeryCodeCom\Suus\Validation\ValidationPolicy;

/**
 * Validates a ShipmentOrder against SUUS API business rules before making the API call.
 * Returns a list of {@see ValidationError} objects (empty = valid).
 *
 * Limits enforced:
 *   - Max 126 kg per package
 *   - Max 800 kg total per order
 *   - Max 124 packages per order
 *   - Max dimensions: 240 x 120 x 220 cm
 *   - Loading date must be a business day in the sender's country, at least +2 business days from today
 *   - incoterms required for international routes
 *   - Address field lengths (WSDL limits): name<=100, street<=50, streetNo<=10, postcode<=10, city<=50, phone<=30
 *   - reference<=50 characters
 *
 * International-only rules (see SUUS WebApi docs, WS PK 1.0) are shaped by an
 * injected {@see ValidationPolicy} (which of them to enforce) and a
 * {@see RouteClassifierInterface} (which routes count as international):
 *   - orderType must be B2B                          (PRJ00345)
 *   - domestic-only services (StdAwizacjaSms, StdWniesienie2, ...Grid2) are unavailable
 *     on international orders; international-only services (...Grid3) are unavailable
 *     on domestic orders
 *   - returnable / stackable packaging is unavailable (PRJ00372 / PRJ00373)
 *
 * @internal This class is not part of the public API and may change without notice.
 *           Use {@see \VeryCodeCom\Suus\SuusClient::validate()} instead.
 */
final class ShipmentValidator
{
    private const MAX_WEIGHT_PER_PKG = 126.0;
    private const MAX_TOTAL_WEIGHT   = 800.0;
    private const MAX_PKG_COUNT      = 124;
    private const MAX_LENGTH_CM      = 240.0;
    private const MAX_WIDTH_CM       = 120.0;
    private const MAX_HEIGHT_CM      = 220.0;
    private const MIN_ADVANCE_DAYS   = 2;

    // Minimum heights per package symbol (confirmed from SUUS API responses)
    private const MIN_HEIGHT_CM = [
        'EUR' => 20.0,
    ];

    // Field length limits per WSDL
    private const MAX_REFERENCE_LEN  = 50;
    private const MAX_NAME_LEN       = 100;
    private const MAX_STREET_LEN     = 50;
    private const MAX_STREET_NO_LEN  = 10;
    private const MAX_POSTCODE_LEN   = 10;
    private const MAX_CITY_LEN       = 50;
    private const MAX_PHONE_LEN      = 30;
    private const MAX_COST_GROUP_LEN = 20;
    private const MAX_FREIGHT_LEN    = 50;
    private const CURRENCY_LEN       = 3;

    /**
     * SUUS service symbols available on DOMESTIC orders only, and therefore
     * rejected on international orders (WS PK 1.0, p. 12-14).
     */
    private const DOMESTIC_ONLY_SERVICE_SYMBOLS = [
        'StdAwizacjaSms',                       // SMS pre-advice
        'StdWniesienie2',                       // inside delivery
        'StdDokumentyZwrotneINiezwrotneGrid2',  // return of documents (KR)
    ];

    /**
     * SUUS service symbols available on INTERNATIONAL orders only, and therefore
     * rejected on domestic orders (WS PK 1.0, p. 11).
     */
    private const INTERNATIONAL_ONLY_SERVICE_SYMBOLS = [
        'StdDokumentyZwrotneINiezwrotneGrid3',  // return of documents (GG)
    ];

    public function __construct(private readonly ?BusinessCalendarInterface $calendar = null) {}

    /**
     * @return ValidationError[] List of validation errors; empty = valid.
     */
    public function validate(
        ShipmentOrder $order,
        ?\DateTimeImmutable $referenceDate = null,
        ?BusinessCalendarInterface $calendar = null,
        ?ValidationPolicy $policy = null,
        ?RouteClassifierInterface $classifier = null,
    ): array {
        $cal        = $calendar ?? $this->calendar ?? CalendarFactory::forCountry($order->sender->getCountryCode());
        $policy     = $policy ?? ValidationPolicy::strict();
        $classifier = $classifier ?? new DefaultRouteClassifier();

        $isInternational = $classifier->isInternational($order);

        $errors = [];

        $this->validatePackages($order, $errors);
        $this->validateLoadingDate($order, $errors, $referenceDate, $cal);
        $this->validateIncoterms($order, $errors, $isInternational);
        $this->validateInternationalHeader($order, $errors, $isInternational, $policy);
        $this->validateServiceRouteRestrictions($order, $errors, $isInternational, $policy);
        $this->validateInternationalPackaging($order, $errors, $isInternational, $policy);
        $this->validateFieldLengths($order, $errors);

        return $errors;
    }

    /** @param ValidationError[] $errors */
    private function validatePackages(ShipmentOrder $order, array &$errors): void
    {
        $totalWeight = 0.0;
        $pkgCount    = count($order->packages);

        if ($pkgCount > self::MAX_PKG_COUNT) {
            $errors[] = new ValidationError(
                "Too many packages: {$pkgCount} (SUUS limit: " . self::MAX_PKG_COUNT . ').',
                'packages',
                ValidationCode::PACKAGE_COUNT_EXCEEDED,
            );
        }

        foreach ($order->packages as $i => $pkg) {
            if ($pkg->weightKg > self::MAX_WEIGHT_PER_PKG) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: weightKg {$pkg->weightKg} exceeds max " . self::MAX_WEIGHT_PER_PKG . ' kg per package.',
                    "packages[{$i}].weightKg",
                    ValidationCode::PACKAGE_WEIGHT_EXCEEDED,
                );
            }
            $totalWeight += $pkg->weightKg;

            if ($pkg->lengthCm !== null && $pkg->lengthCm > self::MAX_LENGTH_CM) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: lengthCm {$pkg->lengthCm} exceeds max " . self::MAX_LENGTH_CM . ' cm.',
                    "packages[{$i}].lengthCm",
                    ValidationCode::DIMENSION_EXCEEDED,
                );
            }
            if ($pkg->widthCm !== null && $pkg->widthCm > self::MAX_WIDTH_CM) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: widthCm {$pkg->widthCm} exceeds max " . self::MAX_WIDTH_CM . ' cm.',
                    "packages[{$i}].widthCm",
                    ValidationCode::DIMENSION_EXCEEDED,
                );
            }
            if ($pkg->heightCm !== null && $pkg->heightCm > self::MAX_HEIGHT_CM) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: heightCm {$pkg->heightCm} exceeds max " . self::MAX_HEIGHT_CM . ' cm.',
                    "packages[{$i}].heightCm",
                    ValidationCode::DIMENSION_EXCEEDED,
                );
            }
            $minH = self::MIN_HEIGHT_CM[$pkg->symbol->value] ?? null;
            if ($minH !== null && $pkg->heightCm !== null && $pkg->heightCm < $minH) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: heightCm {$pkg->heightCm} is below the minimum "
                        . "{$minH} cm for {$pkg->symbol->value} packages.",
                    "packages[{$i}].heightCm",
                    ValidationCode::EUR_MIN_HEIGHT,
                );
            }
        }

        if ($totalWeight > self::MAX_TOTAL_WEIGHT) {
            $errors[] = new ValidationError(
                "Total weight {$totalWeight} kg exceeds SUUS limit of " . self::MAX_TOTAL_WEIGHT . ' kg.',
                'packages',
                ValidationCode::TOTAL_WEIGHT_EXCEEDED,
            );
        }
    }

    /** @param ValidationError[] $errors */
    private function validateLoadingDate(ShipmentOrder $order, array &$errors, ?\DateTimeImmutable $referenceDate, BusinessCalendarInterface $calendar): void
    {
        if ($order->loadingDate === null) {
            return; // null = auto-computed, always valid
        }

        $ld      = $order->loadingDate->setTime(0, 0, 0);
        $country = $order->sender->getCountryCode();

        if (!$calendar->isBusinessDay($ld)) {
            $errors[] = new ValidationError(
                'loadingDate ' . $ld->format('Y-m-d') . " is not a business day in {$country} (weekend or public holiday).",
                'loadingDate',
                ValidationCode::LOADING_NOT_BUSINESS_DAY,
            );
            return;
        }

        $minDate = $calendar->minLoadingDate(self::MIN_ADVANCE_DAYS, $referenceDate);
        if ($ld < $minDate) {
            $errors[] = new ValidationError(
                'loadingDate ' . $ld->format('Y-m-d')
                    . ' is too soon - SUUS requires at least ' . self::MIN_ADVANCE_DAYS
                    . ' business days advance notice (earliest: ' . $minDate->format('Y-m-d') . ').',
                'loadingDate',
                ValidationCode::LOADING_TOO_SOON,
            );
        }
    }

    /** @param ValidationError[] $errors */
    private function validateIncoterms(ShipmentOrder $order, array &$errors, bool $isInternational): void
    {
        if ($isInternational && $order->incoterms === null) {
            $from = $order->sender->getCountryCode();
            $to   = $order->receiver->getCountryCode();
            $errors[] = new ValidationError(
                "incoterms is required for non-PL->PL routes (route: {$from}->{$to}).",
                'incoterms',
                ValidationCode::INCOTERMS_REQUIRED,
            );
        }
    }

    /**
     * Header rules specific to international orders, per SUUS WS docs:
     *   - only orderType B2B is supported for international routes (toggle via policy)
     *   - freight and currency must be provided together or not at all (PRJ00387)
     *   - currency must be a 3-letter code; freight <= 50 chars; costGroup <= 20 chars
     *
     * @param ValidationError[] $errors
     */
    private function validateInternationalHeader(ShipmentOrder $order, array &$errors, bool $isInternational, ValidationPolicy $policy): void
    {
        if ($isInternational && $policy->enforceInternationalB2B && $order->orderType !== OrderType::B2B) {
            $errors[] = new ValidationError(
                'International orders support only orderType B2B (SUUS rule).',
                'orderType',
                ValidationCode::INTERNATIONAL_B2B_ONLY,
            );
        }

        $hasFreight  = $order->freight  !== null && $order->freight  !== '';
        $hasCurrency = $order->currency !== null && $order->currency !== '';
        if ($hasFreight !== $hasCurrency) {
            $errors[] = new ValidationError(
                'freight and currency must be provided together or not at all (SUUS rule PRJ00387).',
                $hasFreight ? 'currency' : 'freight',
                ValidationCode::FREIGHT_CURRENCY_PAIR,
            );
        }
        if ($hasCurrency && mb_strlen((string) $order->currency) !== self::CURRENCY_LEN) {
            $errors[] = new ValidationError(
                'currency must be a 3-letter code (e.g. EUR, PLN).',
                'currency',
                ValidationCode::CURRENCY_LENGTH,
            );
        }
        if ($hasFreight && mb_strlen((string) $order->freight) > self::MAX_FREIGHT_LEN) {
            $errors[] = new ValidationError(
                'freight exceeds ' . self::MAX_FREIGHT_LEN . ' characters (SUUS WSDL limit).',
                'freight',
                ValidationCode::FIELD_TOO_LONG,
            );
        }
        if ($order->costGroup !== null && mb_strlen($order->costGroup) > self::MAX_COST_GROUP_LEN) {
            $errors[] = new ValidationError(
                'costGroup exceeds ' . self::MAX_COST_GROUP_LEN . ' characters (SUUS WSDL limit).',
                'costGroup',
                ValidationCode::FIELD_TOO_LONG,
            );
        }
    }

    /**
     * Services carry a route restriction (WS PK 1.0): some are domestic-only
     * (SMS pre-advice, inside delivery, document return KR) and some are
     * international-only (document return GG). Reject any service used on the
     * wrong route. Handles both typed {@see ServiceInterface} objects and legacy
     * service-code strings.
     *
     * @param ValidationError[] $errors
     */
    private function validateServiceRouteRestrictions(ShipmentOrder $order, array &$errors, bool $isInternational, ValidationPolicy $policy): void
    {
        if (!$policy->enforceServiceRouteRestrictions) {
            return;
        }

        foreach ($order->additionalServices as $service) {
            $symbol = $service instanceof ServiceInterface ? $service->getSymbol() : (string) $service;

            if ($isInternational && in_array($symbol, self::DOMESTIC_ONLY_SERVICE_SYMBOLS, true)) {
                $errors[] = new ValidationError(
                    "Service '{$symbol}' is domestic-only and is not available on international orders (SUUS rule).",
                    'additionalServices',
                    ValidationCode::DOMESTIC_ONLY_SERVICE,
                );
            }

            if (!$isInternational && in_array($symbol, self::INTERNATIONAL_ONLY_SERVICE_SYMBOLS, true)) {
                $errors[] = new ValidationError(
                    "Service '{$symbol}' is international-only and is not available on domestic orders (SUUS rule).",
                    'additionalServices',
                    ValidationCode::INTERNATIONAL_ONLY_SERVICE,
                );
            }
        }
    }

    /**
     * Returnable and stackable packaging are not available on international
     * orders (SUUS PRJ00372 / PRJ00373).
     *
     * @param ValidationError[] $errors
     */
    private function validateInternationalPackaging(ShipmentOrder $order, array &$errors, bool $isInternational, ValidationPolicy $policy): void
    {
        if (!$isInternational || !$policy->enforceInternationalPackagingRestrictions) {
            return;
        }

        foreach ($order->packages as $i => $pkg) {
            if ($pkg->returnable !== null && $pkg->returnable > 0) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: returnable packaging is not available on international orders (SUUS rule PRJ00372).",
                    "packages[{$i}].returnable",
                    ValidationCode::RETURNABLE_INTERNATIONAL,
                );
            }
            if ($pkg->stackable !== null && $pkg->stackable > 0) {
                $errors[] = new ValidationError(
                    "packages[{$i}]: stackable packaging is not available on international orders (SUUS rule PRJ00373).",
                    "packages[{$i}].stackable",
                    ValidationCode::STACKABLE_INTERNATIONAL,
                );
            }
        }
    }

    /** @param ValidationError[] $errors */
    private function validateFieldLengths(ShipmentOrder $order, array &$errors): void
    {
        if (mb_strlen($order->reference) > self::MAX_REFERENCE_LEN) {
            $errors[] = new ValidationError(
                'reference exceeds ' . self::MAX_REFERENCE_LEN . ' characters (SUUS WSDL limit).',
                'reference',
                ValidationCode::FIELD_TOO_LONG,
            );
        }

        $this->validateAddress($order->sender,   'sender',   $errors);
        $this->validateAddress($order->receiver, 'receiver', $errors);
    }

    /**
     * @param ValidationError[] $errors
     */
    private function validateAddress(Address $addr, string $prefix, array &$errors): void
    {
        if (mb_strlen($addr->name) > self::MAX_NAME_LEN) {
            $errors[] = new ValidationError("{$prefix}.name exceeds " . self::MAX_NAME_LEN . ' characters.', "{$prefix}.name", ValidationCode::FIELD_TOO_LONG);
        }
        if (mb_strlen($addr->street) > self::MAX_STREET_LEN) {
            $errors[] = new ValidationError("{$prefix}.street exceeds " . self::MAX_STREET_LEN . ' characters.', "{$prefix}.street", ValidationCode::FIELD_TOO_LONG);
        }
        if (mb_strlen($addr->streetNo) > self::MAX_STREET_NO_LEN) {
            $errors[] = new ValidationError("{$prefix}.streetNo exceeds " . self::MAX_STREET_NO_LEN . ' characters.', "{$prefix}.streetNo", ValidationCode::FIELD_TOO_LONG);
        }
        if (mb_strlen($addr->postcode) > self::MAX_POSTCODE_LEN) {
            $errors[] = new ValidationError("{$prefix}.postcode exceeds " . self::MAX_POSTCODE_LEN . ' characters.', "{$prefix}.postcode", ValidationCode::FIELD_TOO_LONG);
        }
        if (mb_strlen($addr->city) > self::MAX_CITY_LEN) {
            $errors[] = new ValidationError("{$prefix}.city exceeds " . self::MAX_CITY_LEN . ' characters.', "{$prefix}.city", ValidationCode::FIELD_TOO_LONG);
        }
        if ($addr->phone !== null && mb_strlen($addr->phone) > self::MAX_PHONE_LEN) {
            $errors[] = new ValidationError("{$prefix}.phone exceeds " . self::MAX_PHONE_LEN . ' characters.', "{$prefix}.phone", ValidationCode::FIELD_TOO_LONG);
        }
        if ($addr->mobilePhone !== null && mb_strlen($addr->mobilePhone) > self::MAX_PHONE_LEN) {
            $errors[] = new ValidationError("{$prefix}.mobilePhone exceeds " . self::MAX_PHONE_LEN . ' characters.', "{$prefix}.mobilePhone", ValidationCode::FIELD_TOO_LONG);
        }
    }
}
