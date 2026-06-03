<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus;

use VeryCodeCom\Suus\Calendar\BusinessCalendarInterface;
use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Dto\DeliveryPoint;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Dto\ShipmentResult;
use VeryCodeCom\Suus\Dto\StatusEvent;
use VeryCodeCom\Suus\Dto\StatusResult;
use VeryCodeCom\Suus\Enum\DocumentType;
use VeryCodeCom\Suus\Exception\SuusApiException;
use VeryCodeCom\Suus\Exception\SuusAuthException;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusResponseParseException;
use VeryCodeCom\Suus\Exception\SuusTransportException;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Internal\Mapper\StatusMapper;
use VeryCodeCom\Suus\Internal\Soap\ResponseParser;
use VeryCodeCom\Suus\Internal\Soap\SoapEnvelopeBuilder;
use VeryCodeCom\Suus\Internal\Validator\ShipmentValidator;
use VeryCodeCom\Suus\Transport\CurlTransport;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PHP client for the SUUS Logistics SOAP API.
 *
 * Quick start:
 *
 *   $client = SuusClient::sandbox('ws_login', 'password');
 *
 *   $result = $client->createShipment(new ShipmentOrder(
 *       reference: 'ORDER-123',
 *       sender:    new Address('Sender GmbH', 'Mainstr.', '1', '10115', 'Berlin', 'DE', phone: '+4930123'),
 *       receiver:  new Address('Recipient', 'Elm St.', '42', '00-001', 'Warsaw', 'PL', phone: '+48600000'),
 *       packages:  [new Package(PackageSymbol::KAR, weightKg: 12.5)],
 *       incoterms: Incoterm::DAP,
 *   ));
 *
 *   echo $result->shipmentNo;  // e.g. OPLKRI2600895
 *
 * All four SUUS API methods are supported:
 *   createShipment()  → addOrder
 *   fetchStatus()     → getEvents
 *   fetchDocument()   → getDocument  (returns raw PDF bytes)
 *   getColliNumbers() → getColliNo
 *
 * @see https://github.com/very-code-com/suus-php
 */
final class SuusClient
{
    private readonly SoapEnvelopeBuilder $builder;
    private readonly ResponseParser      $parser;
    private readonly StatusMapper        $statusMapper;
    private readonly ShipmentValidator   $validator;
    private readonly LoggerInterface     $logger;

    public function __construct(
        private readonly SuusConfig                $config,
        private readonly TransportInterface        $transport = new CurlTransport(),
        ?LoggerInterface                            $logger = null,
        private readonly ?BusinessCalendarInterface $calendar = null,
    ) {
        $this->builder      = new SoapEnvelopeBuilder($config);
        $this->parser       = new ResponseParser();
        $this->statusMapper = new StatusMapper();
        $this->validator    = new ShipmentValidator();
        $this->logger       = $logger ?? new NullLogger();
    }

    // ─────────────────────────────────────────────────────────────────
    // Named constructors
    // ─────────────────────────────────────────────────────────────────

    public static function sandbox(string $login, string $password): self
    {
        return new self(SuusConfig::sandbox($login, $password), new CurlTransport());
    }

    public static function production(string $login, string $password): self
    {
        return new self(SuusConfig::production($login, $password), new CurlTransport());
    }

    // ─────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Create a shipment (SUUS method: addOrder).
     *
     * Validates the order locally first, then calls addOrder.
     * If loadingDate / unloadingDate is null, they are auto-computed using the Polish calendar.
     *
     * @throws SuusValidationException      if local validation fails.
     * @throws SuusAuthException            if SUUS rejects credentials (DRG00001).
     * @throws SuusDuplicateReferenceException if the reference already exists (PRJ00310).
     * @throws SuusApiException             for other SUUS API errors.
     * @throws SuusTransportException       on network / HTTP errors.
     * @throws SuusResponseParseException   if SUUS returns malformed XML.
     */
    public function createShipment(ShipmentOrder $order): ShipmentResult
    {
        $calendar = $this->calendar ?? CalendarFactory::forCountry($order->sender->getCountryCode());

        // Validate before making any network call
        $errors = $this->validator->validate($order, calendar: $calendar);
        if (!empty($errors)) {
            throw new SuusValidationException($errors);
        }

        // Compute loading / unloading dates if not provided
        $loadingDate   = $order->loadingDate   ?? $calendar->minLoadingDate();
        $unloadingDate = $order->unloadingDate ?? $calendar->addBusinessDays($loadingDate, 3);

        $ld = $loadingDate->format('Y-m-d');
        $ud = $unloadingDate->format('Y-m-d');

        $this->logger->info('SUUS: creating shipment', [
            'reference'    => $order->reference,
            'route'        => $order->sender->getCountryCode() . '→' . $order->receiver->getCountryCode(),
            'packages'     => count($order->packages),
            'loading_date' => $ld,
        ]);

        $envelope = $this->builder->buildAddOrder($order, $ld, $ud);
        $xpath    = $this->call('addOrder', $envelope);

        if (!$this->parser->isSuccess($xpath)) {
            $returnCode = $this->parser->returnCode($xpath);
            $errorCodes = $this->parser->errorCodes($xpath);

            $this->logger->error('SUUS: addOrder failed', [
                'reference'   => $order->reference,
                'returnCode'  => $returnCode,
                'errorCodes'  => $errorCodes,
            ]);

            // Auth failure
            foreach ($errorCodes as $e) {
                if ($e['code'] === 'DRG00001') {
                    throw new SuusAuthException('SUUS authentication failed (DRG00001). Check your login/password.');
                }
            }

            // Duplicate reference
            foreach ($errorCodes as $e) {
                if ($e['code'] === 'PRJ00310') {
                    throw new SuusDuplicateReferenceException($order->reference);
                }
            }

            throw new SuusApiException(
                "SUUS addOrder failed [{$returnCode}]: "
                    . implode('; ', array_column($errorCodes, 'message')),
                $returnCode,
                $errorCodes,
            );
        }

        $shipmentNo = $this->parser->shipmentNo($xpath);
        if ($shipmentNo === '') {
            throw new SuusResponseParseException(
                'SUUS addOrder returned success=true but shipmentNo is empty.'
            );
        }

        $this->logger->info('SUUS: shipment created', [
            'reference'  => $order->reference,
            'shipmentNo' => $shipmentNo,
        ]);

        return new ShipmentResult(
            shipmentNo:  $shipmentNo,
            reference:   $order->reference,
            trackingUrl: $this->trackingUrl($shipmentNo),
        );
    }

    /**
     * Poll shipment status and events (SUUS method: getEvents).
     *
     * Note: in the SUUS sandbox environment getEvents always returns PRJ000001
     * (order not found). This is expected sandbox behaviour.
     *
     * @throws SuusApiException           for SUUS API errors.
     * @throws SuusTransportException     on network errors.
     * @throws SuusResponseParseException on malformed XML.
     */
    public function fetchStatus(string $shipmentNo): StatusResult
    {
        $this->logger->debug('SUUS: fetching status', ['shipmentNo' => $shipmentNo]);

        $envelope = $this->builder->buildGetEvents($shipmentNo);
        $xpath    = $this->call('getEvents', $envelope);

        $rawEvents    = $this->parser->events($xpath);
        $latestCode   = '';
        $resolvedStatus = $this->statusMapper->resolveFromEvents($rawEvents);

        $events = [];
        foreach ($rawEvents as $raw) {
            $code = $raw['code'];
            if ($code !== '') {
                $latestCode = $code;
            }

            $dateStr = trim($raw['date'] . ' ' . $raw['time']);
            $dt      = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr)
                    ?: new \DateTimeImmutable();

            $events[] = new StatusEvent(
                rawCode:     $code,
                description: $raw['description'],
                location:    $raw['location'],
                occurredAt:  $dt,
            );
        }

        return new StatusResult(
            status:        $resolvedStatus,
            rawLatestCode: $latestCode,
            events:        $events,
        );
    }

    /**
     * Download a shipping document as raw PDF bytes (SUUS method: getDocument).
     *
     * Available document types: Label (A4), LabelA6, ShippingOrder, LoadingList.
     *
     * @return string Raw PDF bytes - write to file or send with Content-Type: application/pdf.
     *
     * @throws SuusApiException           if SUUS rejects the request.
     * @throws SuusResponseParseException if the returned Base64 is empty or invalid.
     * @throws SuusTransportException     on network errors.
     */
    public function fetchDocument(string $shipmentNo, DocumentType $type = DocumentType::Label): string
    {
        $this->logger->debug('SUUS: fetching document', [
            'shipmentNo'   => $shipmentNo,
            'documentType' => $type->value,
        ]);

        $envelope = $this->builder->buildGetDocument($shipmentNo, $type);
        $xpath    = $this->call('getDocument', $envelope);

        if (!$this->parser->isSuccess($xpath)) {
            $returnCode = $this->parser->returnCode($xpath);
            throw new SuusApiException(
                "SUUS getDocument failed [{$returnCode}] for shipment {$shipmentNo}.",
                $returnCode,
                $this->parser->errorCodes($xpath),
            );
        }

        $base64 = $this->parser->documentBase64($xpath);
        if ($base64 === '') {
            throw new SuusResponseParseException(
                "SUUS getDocument returned an empty document for shipment {$shipmentNo}."
            );
        }

        $pdf = base64_decode($base64, strict: true);
        if ($pdf === false) {
            throw new SuusResponseParseException(
                "SUUS getDocument returned an invalid Base64 payload for shipment {$shipmentNo}."
            );
        }

        return $pdf;
    }

    /**
     * Convenience wrapper - download the default A4 shipping label.
     *
     * @return string Raw PDF bytes.
     */
    public function fetchLabel(string $shipmentNo): string
    {
        return $this->fetchDocument($shipmentNo, DocumentType::Label);
    }

    /**
     * Retrieve individual colli (package) numbers for per-package labels (SUUS: getColliNo).
     *
     * @return string[] List of colli numbers.
     *
     * @throws SuusTransportException     on network errors.
     * @throws SuusResponseParseException on malformed XML.
     */
    public function getColliNumbers(string $shipmentNo): array
    {
        $this->logger->debug('SUUS: fetching colli numbers', ['shipmentNo' => $shipmentNo]);

        $envelope = $this->builder->buildGetColliNo($shipmentNo);
        $xpath    = $this->call('getColliNo', $envelope);

        return $this->parser->colliNumbers($xpath);
    }

    /**
     * Build the tracking URL for a SUUS shipment number.
     */
    public function trackingUrl(string $shipmentNo): string
    {
        return 'https://portal.suus.com/order-details/' . rawurlencode($shipmentNo);
    }

    /**
     * Retrieve all SUUS delivery/pickup points (SUUS method: getDeliveryPoints).
     *
     * Returns a list of depot/point objects available for pickup scheduling.
     *
     * @return DeliveryPoint[]
     *
     * @throws SuusTransportException     on network errors.
     * @throws SuusResponseParseException on malformed XML.
     */
    public function getDeliveryPoints(): array
    {
        $this->logger->debug('SUUS: fetching delivery points');

        $envelope = $this->builder->buildGetDeliveryPoints();
        $xpath    = $this->call('getDeliveryPoints', $envelope);

        $points = [];
        foreach ($this->parser->deliveryPoints($xpath) as $raw) {
            $points[] = new DeliveryPoint(
                symbol:   $raw['symbol'],
                name:     $raw['name'],
                country:  $raw['country'],
                postCode: $raw['postCode'],
                city:     $raw['city'],
                street:   $raw['street'],
                streetNo: $raw['streetNo'],
                timeFrom: $raw['timeFrom'],
                timeTo:   $raw['timeTo'],
            );
        }

        return $points;
    }

    // ─────────────────────────────────────────────────────────────────
    // Internal transport
    // ─────────────────────────────────────────────────────────────────

    /** @throws SuusTransportException|SuusResponseParseException */
    private function call(string $method, string $envelope): \DOMXPath
    {
        $request = new TransportRequest(
            endpoint:       $this->config->getEndpoint(),
            soapAction:     $method,
            body:           $envelope,
            timeout:        $this->config->timeout,
            connectTimeout: $this->config->connectTimeout,
            verifySsl:      $this->config->verifySsl(),
        );

        $response = $this->transport->send($request);

        if (!$response->isSuccess()) {
            throw new SuusTransportException(
                "SUUS returned HTTP {$response->statusCode} for method {$method}."
            );
        }

        return $this->parser->parse($response->body, $method);
    }
}
