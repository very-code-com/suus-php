<?php

/**
 * Example: Download a shipping label as PDF.
 * Note: getDocument always fails in sandbox mode.
 * Run: SUUS_ENV=production php examples/04_fetch_document.php OPLKRI2600895
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Enum\DocumentType;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::production(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$shipmentNo = $argv[1] ?? 'OPLKRI2600895';

try {
    // Download A4 label (default)
    $pdf = $client->fetchLabel($shipmentNo);
    $path = "/tmp/label_{$shipmentNo}.pdf";
    file_put_contents($path, $pdf);
    echo "Label saved to {$path} (" . strlen($pdf) . " bytes)\n";

    // Download A6 thermal label
    $pdfA6 = $client->fetchDocument($shipmentNo, DocumentType::LabelA6);
    $pathA6 = "/tmp/label_a6_{$shipmentNo}.pdf";
    file_put_contents($pathA6, $pdfA6);
    echo "A6 label saved to {$pathA6}\n";

    // Download shipping order
    $shippingOrder = $client->fetchDocument($shipmentNo, DocumentType::ShippingOrder);
    file_put_contents("/tmp/shipping_order_{$shipmentNo}.pdf", $shippingOrder);
    echo "Shipping order saved.\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
