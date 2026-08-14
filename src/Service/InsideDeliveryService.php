<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Inside delivery (wniesienie) service for B2C shipments.
 *
 * SUUS symbol: StdWniesienie2
 * Includes carrying goods inside the premises. No configuration fields required.
 *
 * @api
 */
final class InsideDeliveryService implements ServiceInterface
{
    public function getSymbol(): string
    {
        return 'StdWniesienie2';
    }

    public function getSoapFields(): array
    {
        return [];
    }
}
