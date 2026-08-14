<?php

/**
 * Example 07 - Dependency injection & testing without hitting the network.
 * ---------------------------------------------------------------------------
 * The SuusClient constructor accepts four injectable collaborators:
 *
 *   new SuusClient(
 *       config:    SuusConfig,                    // required
 *       transport: TransportInterface = new CurlTransport(),
 *       logger:    ?Psr\Log\LoggerInterface = null,     // PSR-3, NullLogger by default
 *       calendar:  ?BusinessCalendarInterface = null,   // null = auto-detect by sender country
 *   )
 *
 * This makes the client trivial to unit-test: inject a stub TransportInterface
 * that returns canned XML and assert on the parsed result - no cURL, no network.
 * This is exactly how tests/Unit/SuusClientTest.php works.
 *
 * Run:
 *   php examples/07_di_and_testing.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Calendar\GermanCalendar;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportRequest;
use VeryCodeCom\Suus\Transport\TransportResponse;
use VeryCodeCom\Suus\Exception\SuusException;
use Psr\Log\AbstractLogger;

// ---------------------------------------------------------------------------
// 1. A stub transport that returns a canned success response from a fixture.
//    Because it implements TransportInterface, no real HTTP call is made.
// ---------------------------------------------------------------------------
$stubTransport = new class implements TransportInterface {
    public function send(TransportRequest $request): TransportResponse
    {
        // You can also inspect $request->body here to assert on the XML you built.
        $xml = (string) file_get_contents(__DIR__ . '/../tests/Fixtures/add_order_success.xml');
        return new TransportResponse(200, $xml);
    }
};

// ---------------------------------------------------------------------------
// 2. A tiny PSR-3 logger that prints structured log lines, to show the
//    client's logging hooks (info on create, error on failure).
// ---------------------------------------------------------------------------
$logger = new class extends AbstractLogger {
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $ctx = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "  [LOG {$level}] {$message}{$ctx}\n";
    }
};

// ---------------------------------------------------------------------------
// 3. Assemble the client with all collaborators injected. We force a
//    GermanCalendar to show how to override calendar auto-detection.
// ---------------------------------------------------------------------------
$client = new SuusClient(
    config:    SuusConfig::sandbox('login', 'pass'),
    transport: $stubTransport,
    logger:    $logger,
    calendar:  new GermanCalendar(),
);

$order = new ShipmentOrder(
    reference: 'DI-DEMO-001',
    sender:    new Address('Versender GmbH', 'Musterstr.', '1', '10115', 'Berlin', 'DE', phone: '+4930123'),
    receiver:  new Address('Empfänger GmbH', 'Hauptstr.', '10', '80331', 'Munich', 'DE', phone: '+4989654'),
    packages:  [new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 40.0, widthCm: 30.0, heightCm: 20.0)],
    incoterms: Incoterm::DAP,
    orderType: OrderType::B2B,
);

echo "Creating shipment through a stubbed transport (no network):\n";

try {
    $result = $client->createShipment($order);
    echo "Result: shipmentNo={$result->shipmentNo} reference={$result->reference}\n";
    echo "Tracking: {$result->trackingUrl}\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}

// ---------------------------------------------------------------------------
// 4. Debug mode: with SuusConfig(debug: true) the client attaches the raw SUUS
//    response to every exception and logs a full debug report (message + raw
//    XML + stack trace). Here we feed a fixture that mimics a bare BTN0001
//    ("service temporarily unavailable") error to show the raw output.
// ---------------------------------------------------------------------------
echo "\n--- Debug mode (raw response + stack trace) ---\n";

$failingTransport = new class implements TransportInterface {
    public function send(TransportRequest $request): TransportResponse
    {
        $xml = (string) file_get_contents(__DIR__ . '/../tests/Fixtures/add_order_service_unavailable.xml');
        return new TransportResponse(200, $xml);
    }
};

$debugClient = new SuusClient(
    config:    new SuusConfig('login', 'pass', sandbox: true, debug: true),
    transport: $failingTransport,
    logger:    $logger,   // the debug report is logged here at error level
    calendar:  new GermanCalendar(),
);

try {
    $debugClient->createShipment($order);
} catch (SuusException $e) {
    echo "Caught: {$e->getMessage()}\n";
    // getRawResponse() is always populated (independent of the debug flag):
    echo "Raw response starts with: " . substr((string) $e->getRawResponse(), 0, 38) . "...\n";
    // getDebugReport() bundles message + raw response + stack trace:
    echo "Debug report has stack trace? "
        . (str_contains($e->getDebugReport(), '--- Stack trace ---') ? 'yes' : 'no') . "\n";
}

echo "\nSwap the fixture (e.g. add_order_auth_error.xml or\n";
echo "add_order_service_unavailable.xml) to exercise the error paths in tests.\n";
