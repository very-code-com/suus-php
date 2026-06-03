<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * Incoterms codes supported by SUUS Logistics.
 * Required for all non PL→PL routes.
 */
enum Incoterm: string
{
    case EXW = 'EXW';
    case FCA = 'FCA';
    case FAS = 'FAS';
    case FOB = 'FOB';
    case CFR = 'CFR';
    case CIF = 'CIF';
    case CPT = 'CPT';
    case CIP = 'CIP';
    case DAP = 'DAP';
    case DDP = 'DDP';
}
