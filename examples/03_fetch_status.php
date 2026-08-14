<?php

/**
 * Example 03 - Track a shipment: fetch its normalized status and full event log.
 * ---------------------------------------------------------------------------
 * `fetchStatus()` calls SUUS `getEvents` and returns a StatusResult with:
 *   - status         : a normalized ShipmentStatus enum (see mapping below)
 *   - rawLatestCode  : the most recent native SUUS event code
 *   - events[]       : the full StatusEvent history (code, description, location, time)
 *
 * SUUS native codes are mapped to a small, stable set of ShipmentStatus values:
 *   Created   <- J_CR, KOL, M_KOL
 *   InTransit <- LOAD, ZALF, ZAL, M_DYS, WTRF, ...
 *   Delivered <- ROZF, UNDI, UNLO
 *   Cancelled <- ANUL
 *   Failed    <- ZWRON, ZTF
 *
 * In the sandbox getEvents always returns PRJ000001, so run this against
 * production with a real shipment number to see actual events.
 *
 * Run:
 *   SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php examples/03_fetch_status.php OPLKRI2600895
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Enum\ShipmentStatus;
use VeryCodeCom\Suus\Exception\SuusException;

// getEvents returns real data only on production.
$client = SuusClient::production(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$shipmentNo = $argv[1] ?? 'OPLKRI2600895';

try {
    $status = $client->fetchStatus($shipmentNo);

    echo "Shipment    : {$shipmentNo}\n";
    echo "Status      : {$status->status->value}\n";
    echo "Latest code : {$status->rawLatestCode}\n";
    echo "Delivered?  : " . ($status->isDelivered() ? 'yes' : 'no') . "\n";
    echo "Final state?: " . ($status->isFinal() ? 'yes' : 'no') . "\n";

    // React to the normalized status with an exhaustive match.
    $message = match ($status->status) {
        ShipmentStatus::Pending   => 'Order registered, awaiting pickup.',
        ShipmentStatus::Created   => 'Shipment created and scheduled.',
        ShipmentStatus::InTransit => 'On its way to the recipient.',
        ShipmentStatus::Delivered => 'Delivered to the recipient. ',
        ShipmentStatus::Cancelled => 'Order was cancelled.',
        ShipmentStatus::Failed    => 'Delivery failed / returned to sender.',
    };
    echo "Summary     : {$message}\n";

    echo "\nEvent history (oldest -> newest):\n";
    if ($status->events === []) {
        echo "  (no events yet)\n";
    }
    foreach ($status->events as $event) {
        $line = sprintf(
            '  [%s] %-6s %s',
            $event->occurredAt->format('Y-m-d H:i'),
            $event->rawCode,
            $event->description,
        );
        if ($event->location !== '') {
            $line .= " ({$event->location})";
        }
        echo $line . "\n";
    }
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
