<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Soap;

/**
 * A parsed SUUS response: the queryable DOMXPath plus the raw response body it
 * was parsed from. The raw body is retained so the client can attach it to
 * exceptions when debugging is enabled.
 *
 * @internal Not part of the public API; may change without notice.
 */
final class ParsedResponse
{
    public function __construct(
        public readonly \DOMXPath $xpath,
        public readonly string $raw,
    ) {
    }
}
