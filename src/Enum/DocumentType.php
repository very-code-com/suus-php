<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS document types for the getDocument API call.
 */
enum DocumentType: string
{
    case Label         = 'label';
    case LabelA6       = 'labelA6';
    case ShippingOrder = 'shippingOrder';
    case LoadingList   = 'loadingList';
}
