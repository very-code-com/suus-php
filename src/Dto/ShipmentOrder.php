<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Dto;

use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\ShipmentCategory;
use VeryCodeCom\Suus\Service\ServiceInterface;

/**
 * Full input for the SUUS addOrder SOAP call.
 *
 * - If $loadingDate is null, SuusClient will compute the next valid Polish
 *   business day that is at least 2 business days from today.
 * - If $unloadingDate is null, SuusClient will compute loadingDate + 3 business days.
 * - $incoterms is required for all non-PL->PL routes (SUUS rule).
 *   For PL->PL shipments leave it null.
 * - $freight / $currency are optional and only meaningful for international routes.
 *   They must be provided together or not at all (SUUS rule PRJ00387).
 * - $costGroup is an optional cost-group tag (e.g. "/SI"), max 20 chars.
 * - $additionalServices accepts either typed ServiceInterface objects (recommended)
 *   or plain SUUS service code strings for services this library does not model yet,
 *   e.g. [new CodService(500.00)] or ['RohligCOD'].
 */
final class ShipmentOrder
{
    /**
     * @param Package[]                     $packages          At least one package required.
     * @param array<ServiceInterface|string> $additionalServices Optional typed service objects or raw code strings.
     */
    public function __construct(
        /** Your unique reference (max ~50 chars). Sent as header/reference in addOrder. */
        public readonly string $reference,
        public readonly Address $sender,
        public readonly Address $receiver,
        public readonly array $packages,
        /** Requested pickup date. null = auto-computed (+2 PL business days). */
        public readonly ?\DateTimeImmutable $loadingDate = null,
        /** Requested delivery date. null = auto-computed (loadingDate + 3 days). */
        public readonly ?\DateTimeImmutable $unloadingDate = null,
        /** Required for all non-PL->PL routes. */
        public readonly ?Incoterm $incoterms = null,
        public readonly OrderType $orderType = OrderType::B2C,
        public readonly ShipmentCategory $category = ShipmentCategory::DROBNICA,
        /** Short description of the goods being shipped. */
        public readonly string $descriptionOfGoods = '',
        public readonly string $remarks = '',
        public readonly array $additionalServices = [],
        /** Optional cost-group tag (header/costGroup), max 20 chars, e.g. "/SI". */
        public readonly ?string $costGroup = null,
        /** Freight value for international orders (header/freight). Requires $currency. */
        public readonly ?string $freight = null,
        /** Freight currency (3 chars) for international orders (header/currency). Requires $freight. */
        public readonly ?string $currency = null,
    ) {
        if (empty($packages)) {
            throw new \InvalidArgumentException('ShipmentOrder requires at least one Package.');
        }
    }

    public function isInternational(): bool
    {
        return !($this->sender->getCountryCode() === 'PL'
              && $this->receiver->getCountryCode() === 'PL');
    }
}
