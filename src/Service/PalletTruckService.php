<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Pallet truck (paleciak) service for delivery requiring a pallet jack.
 *
 * SUUS symbol: StdPaleciak
 *
 * @api
 */
final class PalletTruckService implements ServiceInterface
{
    public function getSymbol(): string
    {
        return 'StdPaleciak';
    }

    public function getSoapFields(): array
    {
        return [
            'bool1' => true,
        ];
    }
}
