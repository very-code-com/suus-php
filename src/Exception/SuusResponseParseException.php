<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when the SOAP XML response from SUUS cannot be parsed
 * (malformed XML, unexpected structure, missing required elements).
 */
class SuusResponseParseException extends SuusException {}
