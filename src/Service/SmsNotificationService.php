<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * B2C SMS delivery notification (awizacja SMS).
 *
 * SUUS symbol: StdAwizacjaSms
 * Sends an SMS to the consignee before delivery. No configuration fields required.
 *
 * @api
 */
final class SmsNotificationService implements ServiceInterface
{
    public function getSymbol(): string
    {
        return 'StdAwizacjaSms';
    }

    public function getSoapFields(): array
    {
        return [];
    }
}
