<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS package type symbols.
 * Used in the <symbol> field of each package in addOrder.
 *
 * KAR - karton (cardboard box)
 * EUR - europallet
 * JED - jednorazowa paleta (disposable pallet)
 * PLT - pallet
 * SKR - skrzynia (crate/chest)
 * ROL - rolka (roll)
 * AGD - AGD appliance
 * INN - inny (other)
 * WIA - wiaderko (bucket)
 * DHP - duży heavy package
 * CHP - ciężki heavy package
 * DPL - double pallet
 * HB   - half-block
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
