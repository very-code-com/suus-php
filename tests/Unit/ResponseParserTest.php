<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Internal\Soap\ResponseParser;
use VeryCodeCom\Suus\Exception\SuusResponseParseException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ResponseParser - critical to verify the namespace quirk handling:
 * SUUS responses have reversed xmlns:cw / xmlns:ns1 bindings, so child
 * elements (result, success, shipmentNo, etc.) must be queried without namespace.
 */
final class ResponseParserTest extends TestCase
{
    private ResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResponseParser();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name . '.xml');
    }

    public function testParseSuccessResponseIsTrue(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_success'));
        $this->assertTrue($this->parser->isSuccess($xpath));
    }

    public function testParseFailureResponseIsFalse(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_duplicate_ref'));
        $this->assertFalse($this->parser->isSuccess($xpath));
    }

    public function testExtractShipmentNo(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_success'));
        $this->assertSame('OPLKRI2600895', $this->parser->shipmentNo($xpath));
    }

    public function testExtractReturnCode(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_success'));
        $this->assertSame('CWS0001', $this->parser->returnCode($xpath));
    }

    public function testExtractReturnDescWhenPresent(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_service_unavailable'));
        $this->assertSame('BTN0001', $this->parser->returnCode($xpath));
        $this->assertSame('Service temporarily unavailable', $this->parser->returnDesc($xpath));
    }

    public function testReturnDescIsEmptyWhenAbsent(): void
    {
        $xpath = $this->parser->parse($this->fixture('add_order_success'));
        $this->assertSame('', $this->parser->returnDesc($xpath));
    }

    public function testExtractErrorCodes(): void
    {
        $xpath  = $this->parser->parse($this->fixture('add_order_duplicate_ref'));
        $errors = $this->parser->errorCodes($xpath);

        $this->assertCount(1, $errors);
        $this->assertSame('PRJ00310', $errors[0]['code']);
        $this->assertNotEmpty($errors[0]['message']);
    }

    public function testExtractEventsFromGetEventsResponse(): void
    {
        $xpath  = $this->parser->parse($this->fixture('get_events_response'));
        $events = $this->parser->events($xpath);

        $this->assertCount(4, $events);
        $this->assertSame('J_CR', $events[0]['code']);
        $this->assertSame('UNDI', $events[3]['code']);
        $this->assertSame('Kraków', $events[0]['location']);
    }

    public function testExtractDocumentBase64(): void
    {
        $xpath   = $this->parser->parse($this->fixture('get_document_response'));
        $base64  = $this->parser->documentBase64($xpath);

        $this->assertNotEmpty($base64);
        // Verify it is valid base64
        $this->assertNotFalse(base64_decode($base64, strict: true));
    }

    public function testParseThrowsOnInvalidXml(): void
    {
        $this->expectException(SuusResponseParseException::class);
        $this->parser->parse('<this is not valid xml >>>');
    }

    public function testAuthErrorCodeDetected(): void
    {
        $xpath  = $this->parser->parse($this->fixture('add_order_auth_error'));
        $errors = $this->parser->errorCodes($xpath);

        $this->assertCount(1, $errors);
        $this->assertSame('DRG00001', $errors[0]['code']);
    }

    public function testExtractColliNumbers(): void
    {
        $xpath   = $this->parser->parse($this->fixture('get_colli_numbers_response'));
        $numbers = $this->parser->colliNumbers($xpath);

        $this->assertCount(3, $numbers);
        $this->assertSame('KRKRI2600895-1', $numbers[0]);
        $this->assertSame('KRKRI2600895-2', $numbers[1]);
        $this->assertSame('KRKRI2600895-3', $numbers[2]);
    }

    public function testColliNumbersReturnsEmptyArrayWhenNonePresent(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
              <SOAP-ENV:Body><result><success>true</success></result></SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $xpath = $this->parser->parse($xml);
        $this->assertSame([], $this->parser->colliNumbers($xpath));
    }

    public function testParseThrowsWithMethodContextInMessage(): void
    {
        $this->expectException(SuusResponseParseException::class);
        $this->expectExceptionMessageMatches('/addOrder/');
        $this->parser->parse('<bad xml>', 'addOrder');
    }

    public function testErrorCodesReturnsEmptyWhenNoErrorBlock(): void
    {
        $xpath  = $this->parser->parse($this->fixture('add_order_success'));
        $errors = $this->parser->errorCodes($xpath);
        $this->assertSame([], $errors);
    }
}
