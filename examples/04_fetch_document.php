<?php

/**
 * Example 04 - Download shipment documents and per-package (colli) numbers.
 * ---------------------------------------------------------------------------
 * `fetchDocument()` calls SUUS `getDocument` and returns the raw PDF bytes for
 * one of four document types:
 *
 *   DocumentType::Label         -> standard A4 shipping label
 *   DocumentType::LabelA6       -> A6 thermal-printer label (Zebra etc.)
 *   DocumentType::ShippingOrder -> shipping order (list przewozowy)
 *   DocumentType::LoadingList   -> consolidated loading list
 *
 * `fetchLabel()` is a shortcut for fetchDocument(..., DocumentType::Label).
 * `getColliNumbers()` returns the per-package tracking numbers you need to
 * request individual colli labels.
 *
 * In the sandbox getDocument and getColliNo always fail with PRJ000001, so run
 * this against production with a real shipment number.
 *
 * Run:
 *   SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php examples/04_fetch_document.php OPLKRI2600895
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
$outDir     = sys_get_temp_dir();

/**
 * Small helper: fetch a document type and write it to disk.
 */
$download = static function (SuusClient $client, string $shipmentNo, DocumentType $type, string $outDir): void {
    $pdf  = $client->fetchDocument($shipmentNo, $type);
    $path = sprintf('%s/%s_%s.pdf', $outDir, $type->value, $shipmentNo);
    file_put_contents($path, $pdf);
    printf("  %-14s -> %s (%d bytes)\n", $type->value, $path, strlen($pdf));
};

try {
    echo "Downloading documents for {$shipmentNo}:\n";

    // The standard A4 label via the convenience shortcut ...
    $label = $client->fetchLabel($shipmentNo);
    $labelPath = "{$outDir}/label_{$shipmentNo}.pdf";
    file_put_contents($labelPath, $label);
    printf("  %-14s -> %s (%d bytes)\n", 'label', $labelPath, strlen($label));

    // ... and the other document types explicitly.
    $download($client, $shipmentNo, DocumentType::LabelA6, $outDir);
    $download($client, $shipmentNo, DocumentType::ShippingOrder, $outDir);
    $download($client, $shipmentNo, DocumentType::LoadingList, $outDir);

    // Per-package tracking numbers (colli).
    echo "\nColli (per-package) numbers:\n";
    $colli = $client->getColliNumbers($shipmentNo);
    if ($colli === []) {
        echo "  (none returned)\n";
    }
    foreach ($colli as $i => $number) {
        printf("  #%d %s\n", $i + 1, $number);
    }
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
