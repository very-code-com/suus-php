<?php

/**
 * Example: Dependency injection and testing without network calls.
 * This pattern is used in the test suite - see tests/Unit/SuusClientTest.php.
 * Run: php examples/07_di_and_testing.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportRequest;
use VeryCodeCom\Suus\Transport\TransportResponse;

// Minimal stub transport - returns a canned XML response
$stubTransport = new class implements TransportInterface {
    public function send(TransportRequest $request): TransportResponse
    {
        $xml = file_get_contents(__DIR__ . '/../tests/Fixtures/add_order_success.xml');
        return new TransportResponse(200, $xml);
    }
};

$client = new SuusClient(
    config:    SuusConfig::sandbox('login', 'pass'),
    transport: $stubTransport,
);

echo "Client created with stub transport - no real network calls will be made.\n";
echo "See tests/Unit/SuusClientTest.php for a full PHPUnit example.\n";
