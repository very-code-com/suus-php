<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS package type symbols, sent in the <symbol> field of each package in addOrder.
 *
 * Polish names below are taken verbatim from the "Opakowania systemowe" table in
 * the SUUS WebApi documentation (WS PK 1.0, p. 19):
 *
 *   KAR - karton              (cardboard box)
 *   EUR - pal. EUR            (EUR pallet)
 *   JED - paleta jednorazowa  (disposable pallet)
 *   PLT - paleta przem.       (industrial pallet)
 *   SKR - skrzynia            (crate)
 *   ROL - rolka               (roll)
 *   AGD - gabaryt AGD         (large household appliance)
 *   INN - inne przeładunek    (other / re-handling)
 *   WIA - wiązka              (bundle)
 *   DHP - paleta DHP
 *   CHP - paleta chep         (CHEP pallet)
 *   DPL - pojemnik DPPL       (IBC container)
 *   HB  - hobok
 */
enum PackageSymbol: string
{
    case KAR = 'KAR';
    case EUR = 'EUR';
    case JED = 'JED';
    case PLT = 'PLT';
    case SKR = 'SKR';
    case ROL = 'ROL';
    case AGD = 'AGD';
    case INN = 'INN';
    case WIA = 'WIA';
    case DHP = 'DHP';
    case CHP = 'CHP';
    case DPL = 'DPL';
    case HB  = 'HB';
}
