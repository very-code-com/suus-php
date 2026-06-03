<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Shipment confirmation notification by e-mail.
 *
 * SUUS symbol: RohligZatwierdzeniePowiadomienie
 * Sends an e-mail confirmation to sender and/or receiver on shipment acceptance.
 *
 * @api
 */
final class EmailNotificationService implements ServiceInterface
{
    /**
     * @param bool $notifySender   Send confirmation e-mail to the sender (loadingAddress).
     * @param bool $notifyReceiver Send confirmation e-mail to the receiver (unloadingAddress).
     */
    public function __construct(
        public readonly bool $notifySender   = true,
        public readonly bool $notifyReceiver = true,
    ) {}

    public function getSymbol(): string
    {
        return 'RohligZatwierdzeniePowiadomienie';
    }

    public function getSoapFields(): array
    {
        $fields = [];

        if ($this->notifySender) {
            $fields['varchar1'] = '1';
        }
        if ($this->notifyReceiver) {
            $fields['varchar2'] = '1';
        }

        return $fields;
    }
}
