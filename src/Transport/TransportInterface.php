<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Transport;

use VeryCodeCom\Suus\Exception\SuusTransportException;

/**
 * HTTP transport contract.
 * Implement this interface to swap the default cURL transport
 * with a PSR-18 client adapter or a test double.
 */
interface TransportInterface
{
    /**
     * Send the SOAP request and return the raw HTTP response.
     *
     * @throws SuusTransportException on network errors, SSL failures, or timeouts.
     */
    public function send(TransportRequest $request): TransportResponse;
}
