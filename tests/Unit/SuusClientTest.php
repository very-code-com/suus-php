<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Calendar\PolishCalendar;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusAuthException;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusTransportException;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportRequest;
use VeryCodeCom\Suus\Transport\TransportResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SuusClient, using a mock transport to avoid real HTTP calls.
 */
final class SuusClientTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/' . $name . '.xml');
    }

    private function mockTransport(string $responseBody, int $statusCode = 200): TransportInterface
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willReturn(new TransportResponse($statusCode, $responseBody));
        return $transport;
    }

    private function makeClient(TransportInterface $transport): SuusClient
    {
        return new SuusClient(
            SuusConfig::sandbox('test_login', 'test_password'),
            $transport,
            null,
            new PolishCalendar(),
        );
    }

    private function makeOrder(
        string $fromCountry = 'PL',
        string $toCountry = 'DE',
        ?Incoterm $incoterms = Incoterm::DAP,
    ): ShipmentOrder {
        return new ShipmentOrder(
            reference:  'TEST-001',
            sender:     new Address('Sender Co', 'Main St', '1', '00-001', 'Warsaw', $fromCountry, phone: '+48600000'),
            receiver:   new Address('Receiver GmbH', 'Hauptstr.', '5', '10115', 'Berlin', $toCountry, phone: '+4930123'),
            packages:   [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:  $incoterms,
            loadingDate: (new PolishCalendar())->addBusinessDays(new \DateTimeImmutable('today'), 5),
        );
    }

    // ──────────────────────────────────────────────
    // createShipment - success path
    // ──────────────────────────────────────────────

    public function testCreateShipmentReturnsShipmentResult(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));
        $result = $client->createShipment($this->makeOrder());

        $this->assertSame('OPLKRI2600895', $result->shipmentNo);
        $this->assertSame('TEST-001', $result->reference);
        $this->assertStringContainsString('OPLKRI2600895', $result->trackingUrl);
    }

    public function testCreateShipmentTrackingUrlFormat(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));
        $result = $client->createShipment($this->makeOrder());

        $this->assertSame(
            'https://portal.suus.com/order-details/OPLKRI2600895',
            $result->trackingUrl,
        );
    }

    // ──────────────────────────────────────────────
    // createShipment - error paths
    // ──────────────────────────────────────────────

    public function testCreateShipmentThrowsDuplicateReferenceException(): void
    {
        $this->expectException(SuusDuplicateReferenceException::class);

        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_duplicate_ref')));
        $client->createShipment($this->makeOrder());
    }

    public function testCreateShipmentThrowsAuthException(): void
    {
        $this->expectException(SuusAuthException::class);

        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_auth_error')));
        $client->createShipment($this->makeOrder());
    }

    public function testCreateShipmentThrowsTransportExceptionOnHttp500(): void
    {
        $this->expectException(SuusTransportException::class);

        $client = $this->makeClient($this->mockTransport('<error/>', 500));
        $client->createShipment($this->makeOrder());
    }

    // ──────────────────────────────────────────────
    // createShipment - validation
    // ──────────────────────────────────────────────

    public function testCreateShipmentThrowsValidationExceptionForMissingIncoterms(): void
    {
        $this->expectException(SuusValidationException::class);

        // PL → DE without incoterms should fail validation
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));
        $client->createShipment($this->makeOrder(incoterms: null));
    }

    public function testValidationExceptionContainsMeaningfulErrors(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));

        try {
            $client->createShipment($this->makeOrder(incoterms: null));
            $this->fail('Expected SuusValidationException was not thrown.');
        } catch (SuusValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
            $this->assertStringContainsString('incoterms', implode(' ', $e->getErrors()));
        }
    }

    public function testNoValidationErrorForDomesticPlOrder(): void
    {
        // PL → PL without incoterms is valid
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));
        $result = $client->createShipment($this->makeOrder('PL', 'PL', null));

        $this->assertSame('OPLKRI2600895', $result->shipmentNo);
    }

    // ──────────────────────────────────────────────
    // fetchStatus
    // ──────────────────────────────────────────────

    public function testFetchStatusReturnsDeliveredForFullEventChain(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('get_events_response')));
        $result = $client->fetchStatus('OPLKRI2600895');

        $this->assertTrue($result->isDelivered());
        $this->assertSame('UNDI', $result->rawLatestCode);
        $this->assertCount(4, $result->events);
    }

    public function testFetchStatusEventsHaveCorrectRawCodes(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('get_events_response')));
        $result = $client->fetchStatus('OPLKRI2600895');

        $this->assertSame('J_CR', $result->events[0]->rawCode);
        $this->assertSame('KOL',  $result->events[1]->rawCode);
        $this->assertSame('LOAD', $result->events[2]->rawCode);
        $this->assertSame('UNDI', $result->events[3]->rawCode);
    }

    public function testFetchStatusEventLocations(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('get_events_response')));
        $result = $client->fetchStatus('OPLKRI2600895');

        $this->assertSame('Kraków', $result->events[0]->location);
        $this->assertSame('Berlin', $result->events[3]->location);
    }

    // ──────────────────────────────────────────────
    // fetchDocument
    // ──────────────────────────────────────────────

    public function testFetchDocumentReturnsPdfBytes(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('get_document_response')));
        $pdf    = $client->fetchDocument('OPLKRI2600895');

        $this->assertNotEmpty($pdf);
        // Fixture contains a minimal valid base64 PDF fragment starting with %PDF
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testFetchLabelCallsFetchDocument(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('get_document_response'));
                  });

        $client = $this->makeClient($transport);
        $client->fetchLabel('OPLKRI2600895');

        $this->assertNotNull($capturedRequest);
        $this->assertStringContainsString('getDocument', $capturedRequest->soapAction);
        $this->assertStringContainsString('<documentType', $capturedRequest->body);
        $this->assertStringContainsString('label<', $capturedRequest->body);
    }

    // ──────────────────────────────────────────────
    // SOAP XML structure
    // ──────────────────────────────────────────────

    public function testAddOrderXmlContainsLenghtCmTypo(): void
    {
        // SUUS API has a typo in the package dimension field ("lenghtCm" not "lengthCm").
        // This test guards against regressions if someone "fixes" the field name.
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('add_order_success'));
                  });

        $order = new ShipmentOrder(
            reference:   'TEST-DIM',
            sender:      new Address('Sender', 'St', '1', '00-001', 'Warsaw', 'PL', phone: '+48600000'),
            receiver:    new Address('Recv', 'Main', '2', '10115', 'Berlin', 'DE', phone: '+4930123'),
            packages:    [new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 50.0, widthCm: 30.0, heightCm: 20.0)],
            incoterms:   Incoterm::DAP,
            loadingDate: (new PolishCalendar())->addBusinessDays(new \DateTimeImmutable('today'), 5),
        );

        $this->makeClient($transport)->createShipment($order);

        $this->assertStringContainsString('<lenghtCm', $capturedRequest->body);   // intentional SUUS typo
        $this->assertStringNotContainsString('<lengthCm', $capturedRequest->body); // NOT the correct spelling
    }

    public function testAddOrderXmlContainsAuthBlock(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('add_order_success'));
                  });

        $this->makeClient($transport)->createShipment($this->makeOrder());

        $this->assertStringContainsString('<auth xsi:type="cw:Auth">', $capturedRequest->body);
        $this->assertStringContainsString('<login xsi:type="xsd:string">test_login</login>', $capturedRequest->body);
    }

    public function testInternationalOrderIncludesShipperAndConsignee(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('add_order_success'));
                  });

        $this->makeClient($transport)->createShipment($this->makeOrder('PL', 'DE', Incoterm::DAP));

        $this->assertStringContainsString('<shipper',   $capturedRequest->body);
        $this->assertStringContainsString('<consignee', $capturedRequest->body);
    }

    public function testDomesticOrderExcludesShipperAndConsignee(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('add_order_success'));
                  });

        $this->makeClient($transport)->createShipment($this->makeOrder('PL', 'PL', null));

        $this->assertStringNotContainsString('<shipper',   $capturedRequest->body);
        $this->assertStringNotContainsString('<consignee', $capturedRequest->body);
    }

    // ──────────────────────────────────────────────
    // createShipment - auto-computed dates
    // ──────────────────────────────────────────────

    public function testCreateShipmentWithNullLoadingDateAutoComputesDate(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('add_order_success'));
                  });

        $order = new ShipmentOrder(
            reference: 'AUTO-DATE',
            sender:    new Address('Sender', 'Main St', '1', '00-001', 'Warsaw', 'PL', phone: '+48600000'),
            receiver:  new Address('Recv', 'Elm St', '2', '10115', 'Berlin', 'DE', phone: '+4930123'),
            packages:  [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms: Incoterm::DAP,
            // loadingDate intentionally null - should be auto-computed
        );

        $result = $this->makeClient($transport)->createShipment($order);

        $this->assertSame('OPLKRI2600895', $result->shipmentNo);
        // Verify the envelope contains a loadingDate element
        $this->assertStringContainsString('<loadingDate', $capturedRequest->body);
    }

    // ──────────────────────────────────────────────
    // createShipment - generic API error (not auth/duplicate)
    // ──────────────────────────────────────────────

    public function testCreateShipmentThrowsGenericApiExceptionForUnknownError(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:addOrderResponse xmlns:ns1="cw">
                  <result>
                    <success>false</success>
                    <returnCode>CWS9999</returnCode>
                    <errorCodes>
                      <errorCode>
                        <code>DRG99999</code>
                        <message>Unknown system error</message>
                      </errorCode>
                    </errorCodes>
                  </result>
                </ns1:addOrderResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $this->expectException(\VeryCodeCom\Suus\Exception\SuusApiException::class);

        $client = $this->makeClient($this->mockTransport($xml));
        $client->createShipment($this->makeOrder());
    }

    public function testCreateShipmentApiExceptionContainsReturnCode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:addOrderResponse xmlns:ns1="cw">
                  <result>
                    <success>false</success>
                    <returnCode>CWS9999</returnCode>
                    <errorCodes>
                      <errorCode>
                        <code>DRG99999</code>
                        <message>Unknown system error</message>
                      </errorCode>
                    </errorCodes>
                  </result>
                </ns1:addOrderResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        try {
            $this->makeClient($this->mockTransport($xml))->createShipment($this->makeOrder());
            $this->fail('Expected SuusApiException was not thrown.');
        } catch (\VeryCodeCom\Suus\Exception\SuusApiException $e) {
            $this->assertSame('CWS9999', $e->returnCode);
            $this->assertTrue($e->hasCode('DRG99999'));
        }
    }

    // ──────────────────────────────────────────────
    // createShipment - empty shipmentNo
    // ──────────────────────────────────────────────

    public function testCreateShipmentThrowsParseExceptionWhenShipmentNoEmpty(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:addOrderResponse xmlns:ns1="cw">
                  <result>
                    <success>true</success>
                    <returnCode>CWS0001</returnCode>
                  </result>
                  <shipmentNo></shipmentNo>
                </ns1:addOrderResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $this->expectException(\VeryCodeCom\Suus\Exception\SuusResponseParseException::class);

        $this->makeClient($this->mockTransport($xml))->createShipment($this->makeOrder());
    }

    // ──────────────────────────────────────────────
    // fetchDocument - error paths
    // ──────────────────────────────────────────────

    public function testFetchDocumentThrowsApiExceptionWhenSuccessFalse(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:getDocumentResponse xmlns:ns1="cw">
                  <result>
                    <success>false</success>
                    <returnCode>PRJ000001</returnCode>
                  </result>
                </ns1:getDocumentResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $this->expectException(\VeryCodeCom\Suus\Exception\SuusApiException::class);

        $this->makeClient($this->mockTransport($xml))->fetchDocument('OPLKRI2600895');
    }

    public function testFetchDocumentThrowsParseExceptionForEmptyDocument(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:getDocumentResponse xmlns:ns1="cw">
                  <result>
                    <success>true</success>
                    <returnCode>CWS0001</returnCode>
                  </result>
                  <document></document>
                </ns1:getDocumentResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $this->expectException(\VeryCodeCom\Suus\Exception\SuusResponseParseException::class);

        $this->makeClient($this->mockTransport($xml))->fetchDocument('OPLKRI2600895');
    }

    public function testFetchDocumentThrowsParseExceptionForInvalidBase64(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:getDocumentResponse xmlns:ns1="cw">
                  <result>
                    <success>true</success>
                    <returnCode>CWS0001</returnCode>
                  </result>
                  <document>!!!NOT_VALID_BASE64!!!</document>
                </ns1:getDocumentResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $this->expectException(\VeryCodeCom\Suus\Exception\SuusResponseParseException::class);

        $this->makeClient($this->mockTransport($xml))->fetchDocument('OPLKRI2600895');
    }

    // ──────────────────────────────────────────────
    // getColliNumbers
    // ──────────────────────────────────────────────

    public function testGetColliNumbersReturnsListOfNumbers(): void
    {
        $client  = $this->makeClient($this->mockTransport($this->fixture('get_colli_numbers_response')));
        $numbers = $client->getColliNumbers('OPLKRI2600895');

        $this->assertCount(3, $numbers);
        $this->assertSame('KRKRI2600895-1', $numbers[0]);
        $this->assertSame('KRKRI2600895-3', $numbers[2]);
    }

    public function testGetColliNumbersSendsCorrectSoapAction(): void
    {
        $capturedRequest = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')
                  ->willReturnCallback(function (TransportRequest $req) use (&$capturedRequest) {
                      $capturedRequest = $req;
                      return new TransportResponse(200, $this->fixture('get_colli_numbers_response'));
                  });

        $this->makeClient($transport)->getColliNumbers('OPLKRI2600895');

        $this->assertNotNull($capturedRequest);
        $this->assertSame('getColliNo', $capturedRequest->soapAction);
        $this->assertStringContainsString('OPLKRI2600895', $capturedRequest->body);
    }

    // ──────────────────────────────────────────────
    // trackingUrl
    // ──────────────────────────────────────────────

    public function testTrackingUrlEncodesSpecialCharacters(): void
    {
        $client = $this->makeClient($this->mockTransport($this->fixture('add_order_success')));
        $url    = $client->trackingUrl('SHP NO/123');
        $this->assertSame('https://portal.suus.com/order-details/SHP%20NO%2F123', $url);
    }
}
