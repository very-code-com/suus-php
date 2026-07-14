<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Validation;

/**
 * A single structured validation failure: a human-readable message plus an
 * optional field path and machine-readable code.
 *
 * The object is {@see \Stringable}, so it degrades gracefully to its message
 * anywhere a string is expected (echo, implode, string casts). This keeps the
 * "just give me the text" story simple while letting integrators map failures
 * to their own localised UI via {@see self::$code} / {@see self::$field}.
 *
 *   $errors = $client->validate($order);          // ValidationError[]
 *   foreach ($errors as $e) {
 *       echo (string) $e;                          // message
 *       echo $e->code;                             // e.g. "PRJ00372"
 *       echo $e->field;                            // e.g. "packages[0].returnable"
 *   }
 *
 * Codes are listed in {@see ValidationCode}; where SUUS defines an equivalent
 * they reuse the exact SUUS code (WS PK 1.0 error-code table).
 *
 * @api
 */
final class ValidationError implements \Stringable
{
    public function __construct(
        /** Human-readable, developer-facing message (English). */
        public readonly string $message,
        /** Dotted field path this error refers to, e.g. "packages[0].weightKg", or null. */
        public readonly ?string $field = null,
        /** Machine-readable code (see {@see ValidationCode}), or null. */
        public readonly ?string $code = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->message;
    }
}
