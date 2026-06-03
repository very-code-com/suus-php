<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when the HTTP/cURL transport layer fails
 * (network error, non-200 HTTP response, SSL error, timeout).
 */
class SuusTransportException extends SuusException {}
