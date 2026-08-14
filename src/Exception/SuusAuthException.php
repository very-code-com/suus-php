<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Exception;

/**
 * Thrown when SUUS returns error code DRG00001 (authentication failed).
 */
class SuusAuthException extends SuusException {}
