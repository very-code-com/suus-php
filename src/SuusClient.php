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
use VeryCodeCom\Suus\Exception\SuusException;
use VeryCodeCom\Suus\Exception\SuusResponseParseException;
use VeryCodeCom\Suus\Exception\SuusTransportException;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Internal\Mapper\StatusMapper;
use VeryCodeCom\Suus\Internal\Soap\ParsedResponse;
use VeryCodeCom\Suus\Internal\Soap\ResponseParser;
use VeryCodeCom\Suus\Internal\Soap\SoapEnvelopeBuilder;
use VeryCodeCom\Suus\Internal\Validator\ShipmentValidator;
use VeryCodeCom\Suus\Routing\DefaultRouteClassifier;
use VeryCodeCom\Suus\Routing\RouteClassifierInterface;
use VeryCodeCom\Suus\Validation\ValidationError;
use VeryCodeCom\Suus\Validation\ValidationPolicy;
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
 *   createShipment()  -> addOrder
 *   fetchStatus()     -> getEvents
 *   fetchDocument()   -> getDocument  (returns raw PDF bytes)
 *   getColliNumbers() -> getColliNo
 *
 * @see https://github.com/very-code-com/suus-php
 */
final class SuusClient
{
    private readonly SoapEnvelopeBuilder      $builder;
    private readonly ResponseParser           $parser;
    private readonly StatusMapper             $statusMapper;
    private readonly ShipmentValidator        $validator;
    private readonly LoggerInterface          $logger;
    private readonly ValidationPolicy         $policy;
    private readonly RouteClassifierInterface $routeClassifier;

    public function __construct(
        private readonly SuusConfig                $config,
        private readonly TransportInterface        $transport = new CurlTransport(),
        ?LoggerInterface                            $logger = null,
        private readonly ?BusinessCalendarInterface $calendar = null,
        ?ValidationPolicy                           $policy = null,
        ?RouteClassifierInterface                   $routeClassifier = null,
    ) {
        $this->policy          = $policy ?? ValidationPolicy::strict();
        $this->routeClassifier = $routeClassifier ?? new DefaultRouteClassifier();
        $this->builder         = new SoapEnvelopeBuilder($config, $this->routeClassifier);
        $this->parser          = new ResponseParser();
        $this->statusMapper    = new StatusMapper();
        $this->validator       = new ShipmentValidator();
        $this->logger          = $logger ?? new NullLogger();
    }

    // -----------------------------------------------------------------
    // Named constructors
    // -----------------------------------------------------------------

    public static function sandbox(string $login, string $password): self
    {
        return new self(SuusConfig::sandbox($login, $password), new CurlTransport());
    }

    public static function production(string $login, string $password): self
    {
        return new self(SuusConfig::production($login, $password), new CurlTransport());
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------

    /**
     * Run the same local business-rule checks as createShipment() without
     * making any network call, so validation can be surfaced in your own UI
     * before the order is sent.
     *
     * The sender-country business-day calendar is auto-selected (or the one
     * injected into this client is used), exactly as createShipment() does, so
     * the order is never validated against the wrong calendar. The configured
     * ValidationPolicy and RouteClassifier are applied.
     *
     * @return ValidationError[] Structured validation errors; empty array = valid.
     */
    public function validate(ShipmentOrder $order): array
    {
        return $this->validator->validate(
            $order,
            calendar:   $this->resolveCalendar($order),
            policy:     $this->policy,
            classifier: $this->routeClassifier,
        );
    }

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
        $calendar = $this->resolveCalendar($order);

        $errors = $this->validate($order);
        if (!empty($errors)) {
            $this->fail(new SuusValidationException($errors));
        }

        $loadingDate   = $order->loadingDate   ?? $calendar->minLoadingDate();
        $unloadingDate = $order->unloadingDate ?? $calendar->addBusinessDays($loadingDate, 3);

        $ld = $loadingDate->format('Y-m-d');
        $ud = $unloadingDate->format('Y-m-d');

        $this->logger->info('SUUS: creating shipment', [
            'reference'    => $order->reference,
            'route'        => $order->sender->getCountryCode() . '->' . $order->receiver->getCountryCode(),
            'packages'     => count($order->packages),
            'loading_date' => $ld,
        ]);

        $envelope = $this->builder->buildAddOrder($order, $ld, $ud);
        $parsed   = $this->call('addOrder', $envelope);
        $xpath    = $parsed->xpath;

        if (!$this->parser->isSuccess($xpath)) {
            $returnCode = $this->parser->returnCode($xpath);
            $returnDesc = $this->parser->returnDesc($xpath);
            $errorCodes = $this->parser->errorCodes($xpath);

            $this->logger->error('SUUS: addOrder failed', [
                'reference'   => $order->reference,
                'returnCode'  => $returnCode,
                'returnDesc'  => $returnDesc,
                'errorCodes'  => $errorCodes,
            ]);

            foreach ($errorCodes as $e) {
                if ($e['code'] === 'DRG00001') {
                    $this->fail(
                        new SuusAuthException('SUUS authentication failed (DRG00001). Check your login/password.'),
                        $parsed->raw,
                    );
                }
            }

            foreach ($errorCodes as $e) {
                if ($e['code'] === 'PRJ00310') {
                    $this->fail(new SuusDuplicateReferenceException($order->reference), $parsed->raw);
                }
            }

            // Prefer per-field error messages; fall back to returnDesc for bare
            // codes (e.g. BTN0001) that carry no errorCodes entries.
            $detail = implode('; ', array_filter(array_column($errorCodes, 'message')));
            if ($detail === '') {
                $detail = $returnDesc;
            }

            $this->fail(
                new SuusApiException(
                    trim("SUUS addOrder failed [{$returnCode}]: {$detail}"),
                    $returnCode,
                    $errorCodes,
                ),
                $parsed->raw,
            );
        }

        $shipmentNo = $this->parser->shipmentNo($xpath);
        if ($shipmentNo === '') {
            $this->fail(
                new SuusResponseParseException(
                    'SUUS addOrder returned success=true but shipmentNo is empty.'
                ),
                $parsed->raw,
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
     * In the SUUS sandbox getEvents always answers PRJ000001 (order not found),
     * regardless of the shipment number; only production returns real events.
     *
     * @throws SuusApiException           for SUUS API errors.
     * @throws SuusTransportException     on network errors.
     * @throws SuusResponseParseException on malformed XML.
     */
    public function fetchStatus(string $shipmentNo): StatusResult
    {
        $this->logger->debug('SUUS: fetching status', ['shipmentNo' => $shipmentNo]);

        $envelope = $this->builder->buildGetEvents($shipmentNo);
        $xpath    = $this->call('getEvents', $envelope)->xpath;

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
        $parsed   = $this->call('getDocument', $envelope);
        $xpath    = $parsed->xpath;

        if (!$this->parser->isSuccess($xpath)) {
            $returnCode = $this->parser->returnCode($xpath);
            $returnDesc = $this->parser->returnDesc($xpath);
            $this->fail(
                new SuusApiException(
                    trim("SUUS getDocument failed [{$returnCode}] for shipment {$shipmentNo}. {$returnDesc}"),
                    $returnCode,
                    $this->parser->errorCodes($xpath),
                ),
                $parsed->raw,
            );
        }

        $base64 = $this->parser->documentBase64($xpath);
        if ($base64 === '') {
            $this->fail(
                new SuusResponseParseException(
                    "SUUS getDocument returned an empty document for shipment {$shipmentNo}."
                ),
                $parsed->raw,
            );
        }

        $pdf = base64_decode($base64, strict: true);
        if ($pdf === false) {
            $this->fail(
                new SuusResponseParseException(
                    "SUUS getDocument returned an invalid Base64 payload for shipment {$shipmentNo}."
                ),
                $parsed->raw,
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
        $xpath    = $this->call('getColliNo', $envelope)->xpath;

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
        $xpath    = $this->call('getDeliveryPoints', $envelope)->xpath;

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

    // -----------------------------------------------------------------
    // Internal transport
    // -----------------------------------------------------------------

    /** Resolve the business-day calendar: injected one wins, else sender-country default. */
    private function resolveCalendar(ShipmentOrder $order): BusinessCalendarInterface
    {
        return $this->calendar ?? CalendarFactory::forCountry($order->sender->getCountryCode());
    }

    /** @throws SuusTransportException|SuusResponseParseException */
    private function call(string $method, string $envelope): ParsedResponse
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
            $this->fail(
                new SuusTransportException(
                    "SUUS returned HTTP {$response->statusCode} for method {$method}."
                ),
                $response->body,
            );
        }

        try {
            $xpath = $this->parser->parse($response->body, $method);
        } catch (SuusResponseParseException $e) {
            $this->fail($e, $response->body);
        }

        return new ParsedResponse($xpath, $response->body);
    }

    /**
     * Attach the raw SUUS response to an exception and, when debugging is
     * enabled, log a full debug report (message + raw response + stack trace)
     * before throwing. Centralises error surfacing for all API methods.
     */
    private function fail(SuusException $exception, ?string $rawResponse = null): never
    {
        if ($rawResponse !== null) {
            $exception->withRawResponse($rawResponse);
        }

        if ($this->config->debug) {
            $this->logger->error($exception->getDebugReport());
        }

        throw $exception;
    }
}
