<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Routing;

use VeryCodeCom\Suus\Dto\ShipmentOrder;

/**
 * Adapts a plain closure to {@see RouteClassifierInterface} for ergonomic
 * overrides, e.g.:
 *
 *   new CallableRouteClassifier(
 *       fn (ShipmentOrder $o): bool =>
 *           // treat DE->DE as domestic, otherwise fall back to the default rule
 *           ($o->sender->getCountryCode() === 'DE' && $o->receiver->getCountryCode() === 'DE')
 *               ? false
 *               : $o->isInternational(),
 *   );
 *
 * @api
 */
final class CallableRouteClassifier implements RouteClassifierInterface
{
    /** @var callable(ShipmentOrder): bool */
    private $classifier;

    /** @param callable(ShipmentOrder): bool $classifier */
    public function __construct(callable $classifier)
    {
        $this->classifier = $classifier;
    }

    public function isInternational(ShipmentOrder $order): bool
    {
        return ($this->classifier)($order);
    }
}
