<?php

/**
 * Example: Fetch shipment status and events.
 * Note: getEvents always returns PRJ000001 in sandbox mode.
 * Run: SUUS_ENV=production php examples/03_fetch_status.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::production(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$shipmentNo = $argv[1] ?? 'OPLKRI2600895';

try {
    $status = $client->fetchStatus($shipmentNo);

    echo "Shipment: {$shipmentNo}\n";
    echo "Status  : {$status->status->value}\n";
    echo "Latest code: {$status->rawLatestCode}\n";
    echo "Delivered: " . ($status->isDelivered() ? 'yes' : 'no') . "\n";
    echo "\nEvents:\n";

    foreach ($status->events as $event) {
        echo "  [{$event->occurredAt->format('Y-m-d H:i')}] {$event->rawCode} - {$event->description}";
        if ($event->location) {
            echo " ({$event->location})";
        }
        echo "\n";
    }
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
