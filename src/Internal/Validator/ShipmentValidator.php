<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Validator;

use VeryCodeCom\Suus\Calendar\BusinessCalendarInterface;
use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;

/**
 * Validates a ShipmentOrder against SUUS API business rules before making the API call.
 * Returns a list of error messages (empty = valid).
 *
 * Limits enforced:
 *   - Max 126 kg per package
 *   - Max 800 kg total per order
 *   - Max 124 packages per order
 *   - Max dimensions: 240 x 120 x 220 cm
 *   - Loading date must be a business day in the sender's country, at least +2 business days from today
 *   - incoterms required for non-PL->PL routes
 *   - Address field lengths (WSDL limits): name<=100, street<=50, streetNo<=10, postcode<=10, city<=50, phone<=30
 *   - reference<=50 characters
 *
 * @internal This class is not part of the public API and may change without notice.
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

    public function __construct(private readonly ?BusinessCalendarInterface $calendar = null) {}

    /**
     * @return string[] List of validation errors; empty = valid.
     */
    public function validate(ShipmentOrder $order, ?\DateTimeImmutable $referenceDate = null, ?BusinessCalendarInterface $calendar = null): array
    {
        $cal = $calendar ?? $this->calendar ?? CalendarFactory::forCountry($order->sender->getCountryCode());

        $errors = [];

        $this->validatePackages($order, $errors);
        $this->validateLoadingDate($order, $errors, $referenceDate, $cal);
        $this->validateIncoterms($order, $errors);
        $this->validateInternationalHeader($order, $errors);
        $this->validateFieldLengths($order, $errors);

        return $errors;
    }

    /** @param string[] $errors */
    private function validatePackages(ShipmentOrder $order, array &$errors): void
    {
        $totalWeight = 0.0;
        $pkgCount    = count($order->packages);

        if ($pkgCount > self::MAX_PKG_COUNT) {
            $errors[] = "Too many packages: {$pkgCount} (SUUS limit: " . self::MAX_PKG_COUNT . ').';
        }

        foreach ($order->packages as $i => $pkg) {
            if ($pkg->weightKg > self::MAX_WEIGHT_PER_PKG) {
                $errors[] = "packages[{$i}]: weightKg {$pkg->weightKg} exceeds max "
                          . self::MAX_WEIGHT_PER_PKG . ' kg per package.';
            }
            $totalWeight += $pkg->weightKg;

            if ($pkg->lengthCm !== null && $pkg->lengthCm > self::MAX_LENGTH_CM) {
                $errors[] = "packages[{$i}]: lengthCm {$pkg->lengthCm} exceeds max " . self::MAX_LENGTH_CM . ' cm.';
            }
            if ($pkg->widthCm !== null && $pkg->widthCm > self::MAX_WIDTH_CM) {
                $errors[] = "packages[{$i}]: widthCm {$pkg->widthCm} exceeds max " . self::MAX_WIDTH_CM . ' cm.';
            }
            if ($pkg->heightCm !== null && $pkg->heightCm > self::MAX_HEIGHT_CM) {
                $errors[] = "packages[{$i}]: heightCm {$pkg->heightCm} exceeds max " . self::MAX_HEIGHT_CM . ' cm.';
            }
            $minH = self::MIN_HEIGHT_CM[$pkg->symbol->value] ?? null;
            if ($minH !== null && $pkg->heightCm !== null && $pkg->heightCm < $minH) {
                $errors[] = "packages[{$i}]: heightCm {$pkg->heightCm} is below the minimum "
                          . "{$minH} cm for {$pkg->symbol->value} packages.";
            }
        }

        if ($totalWeight > self::MAX_TOTAL_WEIGHT) {
            $errors[] = "Total weight {$totalWeight} kg exceeds SUUS limit of " . self::MAX_TOTAL_WEIGHT . ' kg.';
        }
    }

    /** @param string[] $errors */
    private function validateLoadingDate(ShipmentOrder $order, array &$errors, ?\DateTimeImmutable $referenceDate, BusinessCalendarInterface $calendar): void
    {
        if ($order->loadingDate === null) {
            return; // null = auto-computed, always valid
        }

        $ld      = $order->loadingDate->setTime(0, 0, 0);
        $country = $order->sender->getCountryCode();

        if (!$calendar->isBusinessDay($ld)) {
            $errors[] = 'loadingDate ' . $ld->format('Y-m-d')
                      . " is not a business day in {$country} (weekend or public holiday).";
            return;
        }

        $minDate = $calendar->minLoadingDate(self::MIN_ADVANCE_DAYS, $referenceDate);
        if ($ld < $minDate) {
            $errors[] = 'loadingDate ' . $ld->format('Y-m-d')
                      . ' is too soon - SUUS requires at least ' . self::MIN_ADVANCE_DAYS
                      . ' business days advance notice (earliest: ' . $minDate->format('Y-m-d') . ').';
        }
    }

    /** @param string[] $errors */
    private function validateIncoterms(ShipmentOrder $order, array &$errors): void
    {
        if ($order->isInternational() && $order->incoterms === null) {
            $from = $order->sender->getCountryCode();
            $to   = $order->receiver->getCountryCode();
            $errors[] = "incoterms is required for non-PL->PL routes (route: {$from}->{$to}).";
        }
    }

    /**
     * Header rules specific to international (non-PL->PL) orders, per SUUS WS docs:
     *   - only orderType B2B is supported for international routes
     *   - freight and currency must be provided together or not at all (PRJ00387)
     *   - currency must be a 3-letter code; freight <= 50 chars; costGroup <= 20 chars
     *
     * @param string[] $errors
     */
    private function validateInternationalHeader(ShipmentOrder $order, array &$errors): void
    {
        if ($order->isInternational() && $order->orderType !== OrderType::B2B) {
            $errors[] = 'International orders support only orderType B2B (SUUS rule).';
        }

        $hasFreight  = $order->freight  !== null && $order->freight  !== '';
        $hasCurrency = $order->currency !== null && $order->currency !== '';
        if ($hasFreight !== $hasCurrency) {
            $errors[] = 'freight and currency must be provided together or not at all (SUUS rule PRJ00387).';
        }
        if ($hasCurrency && mb_strlen((string) $order->currency) !== self::CURRENCY_LEN) {
            $errors[] = 'currency must be a 3-letter code (e.g. EUR, PLN).';
        }
        if ($hasFreight && mb_strlen((string) $order->freight) > self::MAX_FREIGHT_LEN) {
            $errors[] = 'freight exceeds ' . self::MAX_FREIGHT_LEN . ' characters (SUUS WSDL limit).';
        }
        if ($order->costGroup !== null && mb_strlen($order->costGroup) > self::MAX_COST_GROUP_LEN) {
            $errors[] = 'costGroup exceeds ' . self::MAX_COST_GROUP_LEN . ' characters (SUUS WSDL limit).';
        }
    }

    /** @param string[] $errors */
    private function validateFieldLengths(ShipmentOrder $order, array &$errors): void
    {
        if (mb_strlen($order->reference) > self::MAX_REFERENCE_LEN) {
            $errors[] = 'reference exceeds ' . self::MAX_REFERENCE_LEN . ' characters (SUUS WSDL limit).';
        }

        $this->validateAddress($order->sender,   'sender',   $errors);
        $this->validateAddress($order->receiver, 'receiver', $errors);
    }

    /**
     * @param string[] $errors
     */
    private function validateAddress(Address $addr, string $prefix, array &$errors): void
    {
        if (mb_strlen($addr->name) > self::MAX_NAME_LEN) {
            $errors[] = "{$prefix}.name exceeds " . self::MAX_NAME_LEN . ' characters.';
        }
        if (mb_strlen($addr->street) > self::MAX_STREET_LEN) {
            $errors[] = "{$prefix}.street exceeds " . self::MAX_STREET_LEN . ' characters.';
        }
        if (mb_strlen($addr->streetNo) > self::MAX_STREET_NO_LEN) {
            $errors[] = "{$prefix}.streetNo exceeds " . self::MAX_STREET_NO_LEN . ' characters.';
        }
        if (mb_strlen($addr->postcode) > self::MAX_POSTCODE_LEN) {
            $errors[] = "{$prefix}.postcode exceeds " . self::MAX_POSTCODE_LEN . ' characters.';
        }
        if (mb_strlen($addr->city) > self::MAX_CITY_LEN) {
            $errors[] = "{$prefix}.city exceeds " . self::MAX_CITY_LEN . ' characters.';
        }
        if ($addr->phone !== null && mb_strlen($addr->phone) > self::MAX_PHONE_LEN) {
            $errors[] = "{$prefix}.phone exceeds " . self::MAX_PHONE_LEN . ' characters.';
        }
        if ($addr->mobilePhone !== null && mb_strlen($addr->mobilePhone) > self::MAX_PHONE_LEN) {
            $errors[] = "{$prefix}.mobilePhone exceeds " . self::MAX_PHONE_LEN . ' characters.';
        }
    }
}
