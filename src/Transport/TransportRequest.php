<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Transport;

/**
 * A single outgoing SOAP-over-HTTP request.
 */
final class TransportRequest
{
    public function __construct(
        public readonly string $endpoint,
        /** SOAP action string, sent in the SOAPAction HTTP header. */
        public readonly string $soapAction,
        /** Full SOAP envelope XML as a string. */
        public readonly string $body,
        /** Total request timeout in seconds. */
        public readonly int $timeout = 30,
        /** TCP connection timeout in seconds. */
        public readonly int $connectTimeout = 10,
        /** Whether to verify the server SSL certificate. */
        public readonly bool $verifySsl = true,
    ) {}
}
