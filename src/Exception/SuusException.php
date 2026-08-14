<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Base exception for all SUUS API errors.
 *
 * Every SUUS exception can optionally carry the raw SUUS response that triggered
 * it. This is populated by the client when debugging is enabled (see
 * {@see \VeryCodeCom\Suus\SuusConfig::$debug}) so that unexpected / unrecognised
 * errors can be inspected verbatim, together with a full stack trace, via
 * {@see self::getDebugReport()}.
 */
class SuusException extends \RuntimeException
{
    /** Raw SUUS response body (XML) associated with this error, if captured. */
    protected ?string $rawResponse = null;

    /** The raw SUUS response body (XML) that triggered this error, or null. */
    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }

    /**
     * Attach the raw SUUS response body to this exception (fluent).
     *
     * @return $this
     */
    public function withRawResponse(?string $rawResponse): static
    {
        $this->rawResponse = $rawResponse;
        return $this;
    }

    /**
     * Build a verbose debug report: exception class + message, the raw SUUS
     * response (when captured) and the full stack trace. Intended for logs or
     * developer-facing output when an unexpected error needs investigation.
     */
    public function getDebugReport(): string
    {
        $report = sprintf('%s: %s', static::class, $this->getMessage());

        if ($this->rawResponse !== null && $this->rawResponse !== '') {
            $report .= "\n\n--- Raw SUUS response ---\n" . $this->rawResponse;
        }

        if ($this->getPrevious() !== null) {
            $report .= "\n\n--- Caused by ---\n"
                . get_class($this->getPrevious()) . ': ' . $this->getPrevious()->getMessage();
        }

        $report .= "\n\n--- Stack trace ---\n" . $this->getTraceAsString();

        return $report;
    }
}
