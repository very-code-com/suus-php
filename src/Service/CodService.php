<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Cash-on-delivery (pobranie) additional service.
 *
 * SUUS symbol: RohligCOD
 * Limits: max 15 000 PLN; only PLN currency supported by SUUS.
 *
 * @api
 */
final class CodService implements ServiceInterface
{
    /**
     * @param float  $amount   COD amount to collect (max 15 000 PLN).
     * @param string $currency Currency code - currently only 'PLN' is accepted by SUUS.
     */
    public function __construct(
        public readonly float  $amount,
        public readonly string $currency = 'PLN',
    ) {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('CodService amount must be > 0.');
        }
        if ($amount > 15_000) {
            throw new \InvalidArgumentException('CodService amount must not exceed 15 000 PLN (SUUS limit).');
        }
    }

    public function getSymbol(): string
    {
        return 'RohligCOD';
    }

    public function getSoapFields(): array
    {
        return [
            'decimal1' => $this->amount,
            'varchar1' => $this->currency,
        ];
    }
}
