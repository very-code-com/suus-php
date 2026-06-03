<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when local validation of a ShipmentOrder fails before the API call.
 * Contains a list of all validation violations found.
 */
class SuusValidationException extends SuusException
{
    /** @param string[] $errors */
    public function __construct(
        public readonly array $errors,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'Shipment validation failed: ' . implode('; ', $errors),
            0,
            $previous,
        );
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
