<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Calendar\PolishCalendar;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\DeliveryPoint;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Internal\Soap\ResponseParser;
use VeryCodeCom\Suus\Internal\Validator\ShipmentValidator;
use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportResponse;
use PHPUnit\Framework\TestCase;

final class FieldLengthAndDeliveryPointTest extends TestCase
{
    private ShipmentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShipmentValidator(new PolishCalendar());
    }

    // ── Reference length ──────────────────────────────────────────────

    public function testReferenceTooLongProducesError(): void
    {
        $order  = $this->makeOrder(reference: str_repeat('X', 51));
        $errors = $this->validator->validate($order);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('reference', $errors[0]);
        $this->assertStringContainsString('50', $errors[0]);
    }

    public function testReferenceExactly50CharsIsValid(): void
    {
        $order  = $this->makeOrder(reference: str_repeat('X', 50));
        $errors = $this->validator->validate($order);
        $this->assertEmpty(array_filter($errors, fn($e) => str_contains($e, 'reference')));
    }

    // ── Sender address field lengths ──────────────────────────────────

    public function testSenderNameTooLongProducesError(): void
    {
        $order  = $this->makeOrder(senderName: str_repeat('A', 101));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'sender.name', '100');
    }

    public function testSenderStreetTooLongProducesError(): void
    {
        $order  = $this->makeOrder(senderStreet: str_repeat('S', 51));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'sender.street', '50');
    }

    public function testSenderStreetNoTooLongProducesError(): void
    {
        $order  = $this->makeOrder(senderStreetNo: str_repeat('1', 11));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'sender.streetNo', '10');
    }

    public function testSenderCityTooLongProducesError(): void
    {
        $order  = $this->makeOrder(senderCity: str_repeat('C', 51));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'sender.city', '50');
    }

    public function testSenderPhoneTooLongProducesError(): void
    {
        $order  = $this->makeOrder(senderPhone: str_repeat('1', 31));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'sender.phone', '30');
    }

    // ── Receiver address field lengths ────────────────────────────────

    public function testReceiverNameTooLongProducesError(): void
    {
        $order  = $this->makeOrder(receiverName: str_repeat('B', 101));
        $errors = $this->validator->validate($order);
        $this->assertContainsErrorAbout($errors, 'receiver.name', '100');
    }

    // ── Valid addresses pass ───────────────────────────────────────────

    public function testValidAddressesProduceNoFieldLengthErrors(): void
    {
        $order  = $this->makeOrder();
        $errors = $this->validator->validate($order);
        // Filter out unrelated errors (e.g., loading date too soon for today)
        $lengthErrors = array_filter($errors, fn($e) => str_contains($e, 'exceeds'));
        $this->assertEmpty($lengthErrors);
    }

    // ── SuusClient throws on field length errors ───────────────────────

    public function testClientThrowsValidationExceptionForLongReference(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())->method('send');

        $client = new SuusClient(
            SuusConfig::sandbox('login', 'pass'),
            $transport,
            null,
            new PolishCalendar(),
        );

        $this->expectException(SuusValidationException::class);

        $client->createShipment($this->makeOrder(reference: str_repeat('R', 51)));
    }

    // ── DeliveryPoint DTO ─────────────────────────────────────────────

    public function testDeliveryPointStoresAllFields(): void
    {
        $dp = new DeliveryPoint(
            symbol:   'KR01',
            name:     'Kraków Depot',
            country:  'PL',
            postCode: '30-001',
            city:     'Kraków',
            street:   'Lipowa',
            streetNo: '5',
            timeFrom: '08:00',
            timeTo:   '17:00',
        );

        $this->assertSame('KR01',         $dp->symbol);
        $this->assertSame('Kraków Depot', $dp->name);
        $this->assertSame('PL',           $dp->country);
        $this->assertSame('30-001',       $dp->postCode);
        $this->assertSame('Kraków',       $dp->city);
        $this->assertSame('Lipowa',       $dp->street);
        $this->assertSame('5',            $dp->streetNo);
        $this->assertSame('08:00',        $dp->timeFrom);
        $this->assertSame('17:00',        $dp->timeTo);
    }

    public function testDeliveryPointDefaultsForTimings(): void
    {
        $dp = new DeliveryPoint('SYM', 'Name', 'PL', '00-001', 'Warsaw', 'Main St', '1');
        $this->assertSame('', $dp->timeFrom);
        $this->assertSame('', $dp->timeTo);
    }

    // ── ResponseParser::deliveryPoints ────────────────────────────────

    public function testResponseParserExtractsDeliveryPoints(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:getDeliveryPointsResponse>
                  <result>
                    <deliveryPoints>
                      <deliveryPoint>
                        <symbol>KR01</symbol>
                        <name>Kraków Depot</name>
                        <country>PL</country>
                        <postCode>30-001</postCode>
                        <city>Kraków</city>
                        <street>Lipowa</street>
                        <streetNo>5</streetNo>
                        <timeFrom>08:00</timeFrom>
                        <timeTo>17:00</timeTo>
                      </deliveryPoint>
                      <deliveryPoint>
                        <symbol>WA01</symbol>
                        <name>Warsaw Hub</name>
                        <country>PL</country>
                        <postCode>00-001</postCode>
                        <city>Warsaw</city>
                        <street>Marszałkowska</street>
                        <streetNo>10</streetNo>
                        <timeFrom>07:00</timeFrom>
                        <timeTo>20:00</timeTo>
                      </deliveryPoint>
                    </deliveryPoints>
                  </result>
                </ns1:getDeliveryPointsResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $parser = new ResponseParser();
        $xpath  = $parser->parse($xml, 'getDeliveryPoints');
        $points = $parser->deliveryPoints($xpath);

        $this->assertCount(2, $points);
        $this->assertSame('KR01',         $points[0]['symbol']);
        $this->assertSame('Kraków Depot', $points[0]['name']);
        $this->assertSame('08:00',        $points[0]['timeFrom']);
        $this->assertSame('WA01',         $points[1]['symbol']);
        $this->assertSame('Warsaw Hub',   $points[1]['name']);
    }

    public function testResponseParserReturnsEmptyArrayWhenNoDeliveryPoints(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
              <SOAP-ENV:Body><result><deliveryPoints/></result></SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $parser = new ResponseParser();
        $xpath  = $parser->parse($xml, 'getDeliveryPoints');
        $this->assertSame([], $parser->deliveryPoints($xpath));
    }

    // ── SuusClient::getDeliveryPoints integration ─────────────────────

    public function testClientGetDeliveryPointsReturnsDtoList(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="cw">
              <SOAP-ENV:Body>
                <ns1:getDeliveryPointsResponse>
                  <result>
                    <deliveryPoints>
                      <deliveryPoint>
                        <symbol>KR01</symbol>
                        <name>Depot</name>
                        <country>PL</country>
                        <postCode>30-001</postCode>
                        <city>Kraków</city>
                        <street>Lipowa</street>
                        <streetNo>5</streetNo>
                        <timeFrom>08:00</timeFrom>
                        <timeTo>16:00</timeTo>
                      </deliveryPoint>
                    </deliveryPoints>
                  </result>
                </ns1:getDeliveryPointsResponse>
              </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML;

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willReturn(new TransportResponse(200, $xml));

        $client = new SuusClient(SuusConfig::sandbox('user', 'pass'), $transport);
        $points = $client->getDeliveryPoints();

        $this->assertCount(1, $points);
        $this->assertInstanceOf(DeliveryPoint::class, $points[0]);
        $this->assertSame('KR01',   $points[0]->symbol);
        $this->assertSame('08:00',  $points[0]->timeFrom);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeOrder(
        string $reference     = 'REF-001',
        string $senderName    = 'Sender Co',
        string $senderStreet  = 'Lipowa',
        string $senderStreetNo = '1',
        string $senderCity    = 'Kraków',
        ?string $senderPhone  = '+48600000',
        string $receiverName  = 'Receiver GmbH',
    ): ShipmentOrder {
        return new ShipmentOrder(
            reference: $reference,
            sender:    new Address($senderName, $senderStreet, $senderStreetNo, '30-001', $senderCity, 'PL', phone: $senderPhone),
            receiver:  new Address($receiverName, 'Hauptstr.', '5', '10115', 'Berlin', 'DE', phone: '+4930123'),
            packages:  [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms: Incoterm::DAP,
            loadingDate: new \DateTimeImmutable('2099-12-01'), // far future to skip date validation
        );
    }

    /** @param string[] $errors */
    private function assertContainsErrorAbout(array $errors, string $field, string $limit): void
    {
        $matching = array_filter($errors, fn($e) => str_contains($e, $field));
        $this->assertNotEmpty($matching, "Expected an error about '{$field}' but got: " . implode(', ', $errors));
        $matching = array_filter($matching, fn($e) => str_contains($e, $limit));
        $this->assertNotEmpty($matching, "Expected '{$limit}' in error for '{$field}'.");
    }
}
