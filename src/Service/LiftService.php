<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Lift (winda) for delivery to upper floors (max 750 kg).
 *
 * SUUS symbol: RohligWinda
 *
 * @api
 */
final class LiftService implements ServiceInterface
{
    public function getSymbol(): string
    {
        return 'RohligWinda';
    }

    public function getSoapFields(): array
    {
        return [
            'bool1' => true,
        ];
    }
}
