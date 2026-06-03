<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when SUUS rejects addOrder because the reference already exists (PRJ00310).
 */
class SuusDuplicateReferenceException extends SuusApiException
{
    public function __construct(string $reference, ?\Throwable $previous = null)
    {
        parent::__construct(
            "SUUS: duplicate reference '{$reference}' (PRJ00310)",
            'PRJ00310',
            [['code' => 'PRJ00310', 'message' => "Duplicate reference: {$reference}"]],
            $previous,
        );
    }
}
