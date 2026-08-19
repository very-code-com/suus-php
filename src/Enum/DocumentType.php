<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Enum;

/**
 * SUUS document types for the getDocument API call.
 *
 * Sent as the <document> element (spec 5.3). Label and LabelA6 can be narrowed to
 * individual packages via colli numbers; LoadingList is keyed by the master waybill
 * number instead of a shipment, so it goes through SuusClient::fetchLoadingList().
 */
enum DocumentType: string
{
    /** Standard A4 shipping label. */
    case Label         = 'label';
    /** A6 label for thermal printers (Zebra etc.). */
    case LabelA6       = 'labelA6';
    /** Shipping order (list przewozowy). */
    case ShippingOrder = 'shippingOrder';
    /** Collective loading list (zbiorczy list przewozowy) - requires a master number. */
    case LoadingList   = 'loadingList';
}
