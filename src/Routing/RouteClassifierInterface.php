<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Routing;

use VeryCodeCom\Suus\Dto\ShipmentOrder;

/**
 * Decides whether a {@see ShipmentOrder} is an international shipment.
 *
 * SUUS treats an order as international whenever the place of loading OR
 * unloading is outside Poland; that decision drives BOTH local validation
 * (international-only rules) AND the wire format (the SOAP builder emits the
 * <shipper>/<consignee> blocks and incoterms/category/freight only for
 * international orders). See the SUUS WebApi documentation (WS PK 1.0, p. 11).
 *
 * Inject a custom implementation into {@see \VeryCodeCom\Suus\SuusClient} when a
 * merchant/contract needs a different definition of "domestic" for how the
 * LIBRARY validates and serialises the order.
 *
 * WARNING: this is a client-side override only. It changes what the library sends
 * (shipper/consignee blocks, incoterms emission) and how it validates, but SUUS
 * classifies each shipment on its own side from the address country codes — any
 * route where a country is not PL is an international product server-side. Verified
 * against the sandbox: a DE->DE order forced to "domestic" is still rejected
 * (BTN0002, missing incoterms). Use this seam only when your SUUS contract/product
 * already supports the treatment you are forcing; it cannot create capability the
 * contract does not include. The default ({@see DefaultRouteClassifier}) preserves
 * the standard "anything except PL->PL is international" rule.
 *
 * @api
 */
interface RouteClassifierInterface
{
    public function isInternational(ShipmentOrder $order): bool;
}
