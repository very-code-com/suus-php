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
 * - "lenghtCm" typo (a SUUS API bug that must not be "fixed" on our side)
 * - International orders include shipper/consignee blocks
 * - Domestic orders exclude shipper/consignee
 * - getDocument sends the document symbol as <document>
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

    // ----------------------------------------------
    // Namespace / SOAP structure
    // ----------------------------------------------

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

    // ----------------------------------------------
    // Auth block
    // ----------------------------------------------

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

    // ----------------------------------------------
    // Package dimensions - SUUS typo guard
    // ----------------------------------------------

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

    // ----------------------------------------------
    // International vs domestic
    // ----------------------------------------------

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

    // ----------------------------------------------
    // Reference and key fields
    // ----------------------------------------------

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

    // ----------------------------------------------
    // getDocument envelope
    // ----------------------------------------------

    public function testGetDocumentContainsShipmentNo(): void
    {
        $xml = $this->builder->buildGetDocument('OPLKRI2600895', DocumentType::Label);
        $this->assertStringContainsString('OPLKRI2600895', $xml);
    }

    /** Spec 5.3 names the element <document>; <documentType> makes SUUS answer PRJ000001. */
    public function testGetDocumentUsesDocumentElementNotDocumentType(): void
    {
        $xml = $this->builder->buildGetDocument('OPLKRI2600895', DocumentType::Label);
        $this->assertStringContainsString('<document xsi:type="xsd:string">label</document>', $xml);
        $this->assertStringNotContainsString('<documentType', $xml);
    }

    public function testGetDocumentOmitsColliNoWhenNoneRequested(): void
    {
        $xml = $this->builder->buildGetDocument('OPLKRI2600895', DocumentType::Label);
        $this->assertStringNotContainsString('<colliNo', $xml);
    }

    public function testGetDocumentWrapsRequestedColliNumbers(): void
    {
        $xml = $this->builder->buildGetDocument(
            'OPLKRI2600895',
            DocumentType::LabelA6,
            ['WEB1705000047', 'WEB1705000048'],
        );

        $this->assertStringContainsString('<colliNo xsi:type="cw:ArrayOfColli">', $xml);
        $this->assertStringContainsString(
            '<colli xsi:type="cw:Colli"><colliNo xsi:type="xsd:string">WEB1705000047</colliNo></colli>',
            $xml,
        );
        $this->assertStringContainsString('WEB1705000048', $xml);
    }

    public function testGetDocumentSupportsReferenceAndMasterNo(): void
    {
        $xml = $this->builder->buildGetDocument('', DocumentType::LoadingList, [], '', 'PKRM150000096');

        $this->assertStringNotContainsString('<shipmentNo', $xml);
        $this->assertStringContainsString('<masterNo xsi:type="xsd:string">PKRM150000096</masterNo>', $xml);

        $byRef = $this->builder->buildGetDocument('', DocumentType::Label, [], 'ORDER-123');
        $this->assertStringContainsString('<reference xsi:type="xsd:string">ORDER-123</reference>', $byRef);
    }

    // ----------------------------------------------
    // getEvents / getColliNo envelopes
    // ----------------------------------------------

    public function testGetEventsContainsShipmentNo(): void
    {
        $xml = $this->builder->buildGetEvents('OPLKRI2600895');
        $this->assertStringContainsString('OPLKRI2600895', $xml);
    }

    /**
     * Spec 5.2 / 5.4: both methods take an ArrayOfShipments. A bare <shipmentNo>
     * leaves the shipment list empty and SUUS answers PRJ000001 for real orders.
     */
    public function testGetEventsAndGetColliNoWrapShipmentInArrayOfShipments(): void
    {
        $envelopes = [
            'getEvents'  => $this->builder->buildGetEvents('OPLKRI2600895'),
            'getColliNo' => $this->builder->buildGetColliNo('OPLKRI2600895'),
        ];

        foreach ($envelopes as $method => $xml) {
            $this->assertStringContainsString('<shipments xsi:type="cw:ArrayOfShipments">', $xml, $method);
            $this->assertStringContainsString('<shipment xsi:type="cw:Shipment">', $xml, $method);
            $this->assertStringContainsString(
                '<shipmentNo xsi:type="xsd:string">OPLKRI2600895</shipmentNo>',
                $xml,
                $method,
            );
        }
    }

    public function testGetEventsAndGetColliNoAcceptReferenceInsteadOfShipmentNo(): void
    {
        $envelopes = [
            'getEvents'  => $this->builder->buildGetEvents('', 'ORDER-123'),
            'getColliNo' => $this->builder->buildGetColliNo('', 'ORDER-123'),
        ];

        foreach ($envelopes as $method => $xml) {
            $this->assertStringContainsString('<reference xsi:type="xsd:string">ORDER-123</reference>', $xml, $method);
            $this->assertStringNotContainsString('<shipmentNo', $xml, $method);
        }
    }
}
