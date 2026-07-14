<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Routing;

use VeryCodeCom\Suus\Dto\ShipmentOrder;

/**
 * Default route classifier: delegates to {@see ShipmentOrder::isInternational()},
 * i.e. every route except PL->PL is international. This is the standard SUUS rule
 * and the behaviour used when no custom classifier is injected.
 *
 * @api
 */
final class DefaultRouteClassifier implements RouteClassifierInterface
{
    public function isInternational(ShipmentOrder $order): bool
    {
        return $order->isInternational();
    }
}
