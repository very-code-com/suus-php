<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

/**
 * Immutable address value object used for sender (loadingAddress) and receiver (unloadingAddress).
 *
 * For international shipments (non-PL->PL) the same address is also used
 * as shipper / consignee - SUUS requires all four fields in that case.
 */
final class Address
{
    public function __construct(
        /** Full company / person name */
        public readonly string $name,
        /** Street name (without number) */
        public readonly string $street,
        /** Building / street number */
        public readonly string $streetNo,
        /** Postal code */
        public readonly string $postcode,
        public readonly string $city,
        /** ISO 3166-1 alpha-2 country code, e.g. 'PL', 'DE', 'AT', 'CH' */
        public readonly string $countryCode,
        /** Landline phone number */
        public readonly ?string $phone = null,
        /** Mobile phone number */
        public readonly ?string $mobilePhone = null,
        /** Name of the contact person at the address */
        public readonly ?string $contactPerson = null,
        /** E-mail address (SUUS uses the field name "e-mail" in their SOAP struct) */
        public readonly ?string $email = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Address name must not be empty.');
        }
        if (trim($city) === '') {
            throw new \InvalidArgumentException('Address city must not be empty.');
        }
        if (trim($postcode) === '') {
            throw new \InvalidArgumentException('Address postcode must not be empty.');
        }
        if (strlen($countryCode) !== 2) {
            throw new \InvalidArgumentException(
                "Country code must be a 2-letter ISO 3166-1 alpha-2 code, got: '{$countryCode}'."
            );
        }
        if ($phone === null && $mobilePhone === null) {
            throw new \InvalidArgumentException(
                "At least one of 'phone' or 'mobilePhone' must be provided for address '{$name}'."
            );
        }
    }

    public function getCountryCode(): string
    {
        return strtoupper($this->countryCode);
    }
}
