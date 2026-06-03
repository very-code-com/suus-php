<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\DocumentType;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Internal\Soap\SoapEnvelopeBuilder;
use VeryCodeCom\Suus\SuusConfig;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the XML structure of all SOAP envelopes produced by SoapEnvelopeBuilder.
 *
 * Critical invariants tested:
 * - Auth block is present and correct
 * - SUUS namespace quirk: xmlns:cw="cw" (request-side, not response-side)
 * - "lenghtCm" typo (SUUS API bug that must NOT be "fixed")
 * - International orders include shipper/consignee blocks
 * - Domestic orders exclude shipper/consignee
 * - getDocument sends correct documentType element
 */
final class SoapEnvelopeBuilderTest extends TestCase
{
    private SoapEnvelopeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SoapEnvelopeBuilder(SuusConfig::sandbox('user123', 'pass456'));
    }

    private function makeOrder(
        string $from = 'PL',
        string $to   = 'DE',
        ?Incoterm $incoterms = Incoterm::DAP,
    ): ShipmentOrder {
        return new ShipmentOrder(
            reference:   'ORDER-007',
            sender:      new Address('Sender Co', 'Lipowa', '1', '30-001', 'Kraków', $from, phone: '+48600111222'),
            receiver:    new Address('Emp GmbH', 'Berliner Str.', '10', '10117', 'Berlin', $to, phone: '+4930999888'),
            packages:    [new Package(PackageSymbol::KAR, weightKg: 15.5, lengthCm: 60.0, widthCm: 40.0, heightCm: 30.0)],
            incoterms:   $incoterms,
            loadingDate: new \DateTimeImmutable('2025-09-15'),
        );
    }

    /** Call buildAddOrder with pre-computed date strings (bypassing the client's calendar logic). */
    private function buildAddOrder(ShipmentOrder $order): string
    {
        return $this->builder->buildAddOrder($order, '2025-09-15', '2025-09-18');
    }

    // ──────────────────────────────────────────────
    // Namespace / SOAP structure
    // ──────────────────────────────────────────────

    public function testEnvelopeHasCorrectSoapNamespace(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('http://schemas.xmlsoap.org/soap/envelope/', $xml);
    }

    public function testEnvelopeHasCwNamespace(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        // Request-side namespace is always xmlns:cw="cw"
        $this->assertStringContainsString('xmlns:cw="cw"', $xml);
    }

    // ──────────────────────────────────────────────
    // Auth block
    // ──────────────────────────────────────────────

    public function testAuthBlockContainsLogin(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('<login xsi:type="xsd:string">user123</login>', $xml);
    }

    public function testAuthBlockContainsPassword(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('<password xsi:type="xsd:string">pass456</password>', $xml);
    }

    // ──────────────────────────────────────────────
    // Package dimensions - SUUS typo guard
    // ──────────────────────────────────────────────

    public function testPackageDimensionsUseLenghtCmTypo(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('<lenghtCm', $xml);
        $this->assertStringNotContainsString('<lengthCm', $xml);
    }

    public function testPackageWeightIsPresent(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('<weightKg', $xml);
        $this->assertStringContainsString('15.5', $xml);
    }

    // ──────────────────────────────────────────────
    // International vs domestic
    // ──────────────────────────────────────────────

    public function testInternationalOrderIncludesShipper(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder('PL', 'DE'));
        $this->assertStringContainsString('<shipper', $xml);
    }

    public function testInternationalOrderIncludesConsignee(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder('PL', 'DE'));
        $this->assertStringContainsString('<consignee', $xml);
    }

    public function testDomesticOrderExcludesShipper(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder('PL', 'PL', null));
        $this->assertStringNotContainsString('<shipper', $xml);
    }

    public function testDomesticOrderExcludesConsignee(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder('PL', 'PL', null));
        $this->assertStringNotContainsString('<consignee', $xml);
    }

    // ──────────────────────────────────────────────
    // Reference and key fields
    // ──────────────────────────────────────────────

    public function testReferenceIsInBodyForAddOrder(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('>ORDER-007<', $xml);
    }

    public function testLoadingDateIsFormattedCorrectly(): void
    {
        $xml = $this->buildAddOrder($this->makeOrder());
        $this->assertStringContainsString('2025-09-15', $xml);
    }

    // ──────────────────────────────────────────────
    // getDocument envelope
    // ──────────────────────────────────────────────

    public function testGetDocumentContainsShipmentNo(): void
    {
        $xml = $this->builder->buildGetDocument('OPLKRI2600895', DocumentType::Label);
        $this->assertStringContainsString('OPLKRI2600895', $xml);
    }

    public function testGetDocumentContainsDocumentTypeElement(): void
    {
        $xml = $this->builder->buildGetDocument('OPLKRI2600895', DocumentType::Label);
        $this->assertStringContainsString('<documentType', $xml);
        $this->assertStringContainsString('>label<', $xml);
    }

    // ──────────────────────────────────────────────
    // getEvents envelope
    // ──────────────────────────────────────────────

    public function testGetEventsContainsShipmentNo(): void
    {
        $xml = $this->builder->buildGetEvents('OPLKRI2600895');
        $this->assertStringContainsString('OPLKRI2600895', $xml);
    }
}
