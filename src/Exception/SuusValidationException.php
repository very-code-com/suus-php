<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

use VeryCodeCom\Suus\Validation\ValidationError;

/**
 * Thrown when local validation of a ShipmentOrder fails before the API call.
 * Contains all validation violations found.
 *
 * Violations are structured {@see ValidationError} objects (code + field +
 * message), exposed by {@see self::getValidationErrors()}. {@see self::getErrors()}
 * flattens them to plain message strings for display. Bare strings passed to the
 * constructor are wrapped transparently.
 */
class SuusValidationException extends SuusException
{
    /** @var ValidationError[] */
    public readonly array $errors;

    /** @param array<ValidationError|string> $errors */
    public function __construct(
        array $errors,
        ?\Throwable $previous = null,
    ) {
        $normalised = array_map(
            static fn (ValidationError|string $e): ValidationError => $e instanceof ValidationError ? $e : new ValidationError($e),
            array_values($errors),
        );
        $this->errors = $normalised;

        parent::__construct(
            'Shipment validation failed: ' . implode('; ', array_map(static fn (ValidationError $e): string => $e->message, $normalised)),
            0,
            $previous,
        );
    }

    /**
     * Plain human-readable messages.
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return array_map(static fn (ValidationError $e): string => $e->message, $this->errors);
    }

    /**
     * Structured validation errors (code + field + message).
     *
     * @return ValidationError[]
     */
    public function getValidationErrors(): array
    {
        return $this->errors;
    }
}
