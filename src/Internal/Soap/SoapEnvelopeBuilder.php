<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Soap;

use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\DocumentType;
use VeryCodeCom\Suus\Routing\DefaultRouteClassifier;
use VeryCodeCom\Suus\Routing\RouteClassifierInterface;
use VeryCodeCom\Suus\Service\ServiceInterface;
use VeryCodeCom\Suus\SuusConfig;

/**
 * Builds raw SOAP XML envelopes for all four SUUS API methods.
 *
 * All knowledge about the SUUS XML structure lives here - SuusClient
 * stays clean and only orchestrates calls.
 *
 * Known SUUS API quirks handled here:
 *  - Auth element uses xsi:type="cw:Auth" with empty <session/>
 *  - Package length field is misspelled: <lenghtCm> (not <lengthCm>)
 *  - Address email element uses hyphen: <e-mail>
 *  - Packages array requires SOAP-ENC:arrayType attribute
 *  - International orders require <shipper> and <consignee> in addition to
 *    <loadingAddress> and <unloadingAddress>
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class SoapEnvelopeBuilder
{
    private readonly RouteClassifierInterface $classifier;

    public function __construct(
        private readonly SuusConfig $config,
        ?RouteClassifierInterface $classifier = null,
    ) {
        $this->classifier = $classifier ?? new DefaultRouteClassifier();
    }

    // -----------------------------------------------------------------
    // Public builders (one per SUUS method)
    // -----------------------------------------------------------------

    public function buildAddOrder(ShipmentOrder $order, string $loadingDate, string $unloadingDate): string
    {
        $header = $this->buildHeaderXml($order, $loadingDate, $unloadingDate);
        $body   = '<order xsi:type="cw:Order">'
                . $header
                . $this->addressXml($order->sender,   'loadingAddress')
                . $this->addressXml($order->receiver, 'unloadingAddress');

        if ($this->classifier->isInternational($order)) {
            $body .= $this->addressXml($order->sender,   'shipper');
            $body .= $this->addressXml($order->receiver, 'consignee');
        }

        $body .= $this->packagesXml($order->packages);

        if (!empty($order->additionalServices)) {
            $body .= $this->additionalServicesXml($order->additionalServices);
        }

        $body .= '</order>';

        return $this->envelope('addOrder', $body);
    }

    public function buildGetEvents(string $shipmentNo): string
    {
        $body = '<shipmentNo xsi:type="xsd:string">' . self::xe($shipmentNo) . '</shipmentNo>';
        return $this->envelope('getEvents', $body);
    }

    public function buildGetDocument(string $shipmentNo, DocumentType $type): string
    {
        $body = '<shipmentNo xsi:type="xsd:string">'   . self::xe($shipmentNo) . '</shipmentNo>'
              . '<documentType xsi:type="xsd:string">' . self::xe($type->value) . '</documentType>';
        return $this->envelope('getDocument', $body);
    }

    public function buildGetColliNo(string $shipmentNo): string
    {
        $body = '<shipmentNo xsi:type="xsd:string">' . self::xe($shipmentNo) . '</shipmentNo>';
        return $this->envelope('getColliNo', $body);
    }

    public function buildGetDeliveryPoints(): string
    {
        return $this->envelope('getDeliveryPoints', '');
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    private function envelope(string $method, string $innerBody): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<SOAP-ENV:Envelope'
             . ' xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
             . ' xmlns:cw="cw"'
             . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
             . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
             . ' xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"'
             . ' SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
             . '<SOAP-ENV:Body>'
             . '<cw:' . $method . '>'
             . $this->authXml()
             . $innerBody
             . '</cw:' . $method . '>'
             . '</SOAP-ENV:Body>'
             . '</SOAP-ENV:Envelope>';
    }

    private function authXml(): string
    {
        return '<auth xsi:type="cw:Auth">'
             . '<session xsi:type="xsd:string"></session>'
             . '<login xsi:type="xsd:string">'    . self::xe($this->config->login)    . '</login>'
             . '<password xsi:type="xsd:string">' . self::xe($this->config->password) . '</password>'
             . '</auth>';
    }

    private function buildHeaderXml(ShipmentOrder $order, string $loadingDate, string $unloadingDate): string
    {
        $desc = $order->descriptionOfGoods !== ''
            ? $order->descriptionOfGoods
            : 'General cargo';

        $xml  = '<header xsi:type="cw:OrderHeader">';
        $xml .= '<reference xsi:type="xsd:string">'         . self::xe($order->reference)        . '</reference>';
        $xml .= '<loadingDate xsi:type="xsd:date">'          . self::xe($loadingDate)             . '</loadingDate>';
        $xml .= '<unloadingDate xsi:type="xsd:date">'        . self::xe($unloadingDate)           . '</unloadingDate>';
        $xml .= '<descriptionOfGoods xsi:type="xsd:string">' . self::xe($desc)                   . '</descriptionOfGoods>';
        $xml .= '<orderType xsi:type="xsd:string">'          . self::xe($order->orderType->value) . '</orderType>';

        if ($this->classifier->isInternational($order) && $order->incoterms !== null) {
            $xml .= '<incoterms xsi:type="xsd:string">' . self::xe($order->incoterms->value) . '</incoterms>';
            $xml .= '<category xsi:type="xsd:string">'  . self::xe($order->category->value)  . '</category>';

            if ($order->freight !== null && $order->currency !== null) {
                $xml .= '<freight xsi:type="xsd:string">'  . self::xe($order->freight)  . '</freight>';
                $xml .= '<currency xsi:type="xsd:string">' . self::xe($order->currency) . '</currency>';
            }
        }

        if ($order->costGroup !== null && $order->costGroup !== '') {
            $xml .= '<costGroup xsi:type="xsd:string">' . self::xe($order->costGroup) . '</costGroup>';
        }

        if ($order->remarks !== '') {
            $xml .= '<remarks xsi:type="xsd:string">' . self::xe($order->remarks) . '</remarks>';
        }

        $xml .= '</header>';
        return $xml;
    }

    private function addressXml(Address $addr, string $tag): string
    {
        $xml  = "<{$tag} xsi:type=\"cw:Address\">";
        $xml .= '<name xsi:type="xsd:string">'     . self::xe($addr->name)        . '</name>';
        $xml .= '<street xsi:type="xsd:string">'   . self::xe($addr->street)      . '</street>';
        $xml .= '<streetNo xsi:type="xsd:string">' . self::xe($addr->streetNo)    . '</streetNo>';
        $xml .= '<postCode xsi:type="xsd:string">' . self::xe($addr->postcode)    . '</postCode>';
        $xml .= '<city xsi:type="xsd:string">'     . self::xe($addr->city)        . '</city>';
        $xml .= '<country xsi:type="xsd:string">'  . self::xe($addr->getCountryCode()) . '</country>';

        if ($addr->phone !== null) {
            $xml .= '<phone xsi:type="xsd:string">' . self::xe($addr->phone) . '</phone>';
        }
        if ($addr->mobilePhone !== null) {
            $xml .= '<mobilePhone xsi:type="xsd:string">' . self::xe($addr->mobilePhone) . '</mobilePhone>';
        }

        $person = $addr->contactPerson ?? $addr->name;
        $xml   .= '<person xsi:type="xsd:string">' . self::xe($person) . '</person>';

        if ($addr->email !== null) {
            // SUUS SOAP struct uses "e-mail" (with hyphen) as the XML element name.
            $xml .= '<e-mail xsi:type="xsd:string">' . self::xe($addr->email) . '</e-mail>';
        }

        $xml .= "</{$tag}>";
        return $xml;
    }

    /** @param Package[] $packages */
    private function packagesXml(array $packages): string
    {
        $count = count($packages);
        $xml   = '<packages SOAP-ENC:arrayType="cw:Package[' . $count . ']" xsi:type="cw:Packages">';

        foreach ($packages as $pkg) {
            $xml .= '<package xsi:type="cw:Package">';
            $xml .= '<symbol xsi:type="xsd:string">'    . self::xe($pkg->symbol->value) . '</symbol>';
            $xml .= '<quantity xsi:type="xsd:integer">1</quantity>';
            $xml .= '<weightKg xsi:type="xsd:decimal">' . number_format($pkg->weightKg, 2, '.', '') . '</weightKg>';

            // SUUS API typo: the field is "lenghtCm" (missing 't' in length)
            if ($pkg->lengthCm !== null) {
                $xml .= '<lenghtCm xsi:type="xsd:integer">' . (int) round($pkg->lengthCm) . '</lenghtCm>';
            }
            if ($pkg->widthCm !== null) {
                $xml .= '<widthCm xsi:type="xsd:integer">'  . (int) round($pkg->widthCm)  . '</widthCm>';
            }
            if ($pkg->heightCm !== null) {
                $xml .= '<heightCm xsi:type="xsd:integer">' . (int) round($pkg->heightCm) . '</heightCm>';
            }
            if ($pkg->returnable !== null) {
                $xml .= '<returnable xsi:type="xsd:integer">' . $pkg->returnable . '</returnable>';
            }
            if ($pkg->stackable !== null) {
                $xml .= '<stackable xsi:type="xsd:integer">' . $pkg->stackable . '</stackable>';
            }

            $xml .= '</package>';
        }

        $xml .= '</packages>';
        return $xml;
    }

    /**
     * Builds the <additionalServices> XML block.
     *
     * Accepts typed ServiceInterface objects (recommended) or plain service code
     * strings. Plain strings produce a <symbol>-only service element.
     *
     * @param array<ServiceInterface|string> $services
     */
    private function additionalServicesXml(array $services): string
    {
        $count = count($services);
        $xml   = '<additionalServices SOAP-ENC:arrayType="cw:AdditionalService[' . $count
               . ']" xsi:type="cw:AdditionalServices">';

        foreach ($services as $svc) {
            $xml .= '<additionalService xsi:type="cw:AdditionalService">';

            if ($svc instanceof ServiceInterface) {
                $xml .= '<symbol xsi:type="xsd:string">' . self::xe($svc->getSymbol()) . '</symbol>';
                foreach ($svc->getSoapFields() as $name => $value) {
                    $xsiType = $this->serviceFieldXsiType($name);
                    $xml    .= '<' . $name . ' xsi:type="' . $xsiType . '">'
                             . self::xe($this->serializeServiceFieldValue($value, $xsiType))
                             . '</' . $name . '>';
                }
            } else {
                // Legacy: plain string service code - emit symbol only.
                $xml .= '<symbol xsi:type="xsd:string">' . self::xe($svc) . '</symbol>';
            }

            $xml .= '</additionalService>';
        }

        $xml .= '</additionalServices>';
        return $xml;
    }

    /** Determine xsi:type from SUUS field name prefix. */
    private function serviceFieldXsiType(string $fieldName): string
    {
        if (str_starts_with($fieldName, 'decimal')) {
            return 'xsd:decimal';
        }
        if (str_starts_with($fieldName, 'bool')) {
            return 'xsd:boolean';
        }
        // varchar*, int* (int01 is typed xsd:string in WSDL despite the name)
        return 'xsd:string';
    }

    /** Serialize a PHP scalar to its SOAP string representation. */
    private function serializeServiceFieldValue(bool|float|string $value, string $xsiType): string
    {
        if ($xsiType === 'xsd:boolean') {
            return $value ? 'true' : 'false';
        }
        if ($xsiType === 'xsd:decimal') {
            return number_format((float) $value, 2, '.', '');
        }
        return (string) $value;
    }

    private static function xe(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
