<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when SUUS returns a business-logic error (non-success response).
 * Contains the SUUS return code and a list of error code/message pairs.
 */
class SuusApiException extends SuusException
{
    /** @param array<array{code: string, message: string}> $errorCodes */
    public function __construct(
        string $message,
        public readonly string $returnCode,
        public readonly array $errorCodes = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Return all SUUS error codes as a flat array of strings, e.g. ['PRJ00310: Duplicate reference'].
     *
     * @return list<string>
     */
    public function getFormattedErrors(): array
    {
        return array_values(array_map(
            fn (array $e) => trim($e['code'] . ': ' . $e['message']),
            $this->errorCodes,
        ));
    }

    public function hasCode(string $code): bool
    {
        foreach ($this->errorCodes as $e) {
            if ($e['code'] === $code) {
                return true;
            }
        }
        return false;
    }
}
