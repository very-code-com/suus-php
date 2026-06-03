<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Transport;

/**
 * Raw HTTP response from the SOAP endpoint.
 */
final class TransportResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {}

    public function isSuccess(): bool
    {
        return $this->statusCode === 200;
    }
}
