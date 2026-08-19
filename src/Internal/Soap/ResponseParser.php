<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Soap;

use VeryCodeCom\Suus\Exception\SuusResponseParseException;

/**
 * Parses raw SOAP XML responses from the SUUS API into a DOMXPath instance.
 *
 * SUUS SOAP namespace quirk (critical):
 *   - Requests send:    xmlns:cw="cw"
 *   - Responses return: xmlns:cw="ns1" on Envelope + xmlns:ns1="cw" on the response element
 *   - Result: child elements (result, success, shipmentNo, errorCodes...) have NO namespace.
 *   - Fix: always query with unqualified XPath (//success, //shipmentNo) - never //cw:success.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class ResponseParser
{
    /**
     * Parse a raw XML string into a DOMXPath ready for querying.
     *
     * @throws SuusResponseParseException if the XML is invalid or unparseable.
     */
    public function parse(string $xml, string $methodContext = ''): \DOMXPath
    {
        $dom    = new \DOMDocument();
        $loaded = @$dom->loadXML($xml);

        if (!$loaded) {
            throw new SuusResponseParseException(
                'SUUS returned invalid XML' . ($methodContext ? " for {$methodContext}" : '') . '.'
            );
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('cw',  'cw');

        return $xpath;
    }

    // -----------------------------------------------------------------
    // Typed helpers used by SuusClient
    // -----------------------------------------------------------------

    /** Extract <result><success> text - "true" means success. */
    public function isSuccess(\DOMXPath $xpath): bool
    {
        return strtolower($this->textOf($xpath, '//result/success')) === 'true';
    }

    public function returnCode(\DOMXPath $xpath): string
    {
        return $this->textOf($xpath, '//result/returnCode');
    }

    /**
     * Extract <result><returnDesc> - a human-readable description SUUS may return
     * alongside the return code (empty for some codes, e.g. BTN0001).
     */
    public function returnDesc(\DOMXPath $xpath): string
    {
        return $this->textOf($xpath, '//result/returnDesc');
    }

    /** @return array<array{code: string, message: string}> */
    public function errorCodes(\DOMXPath $xpath): array
    {
        $errors = [];
        $nodes  = $xpath->query('//errorCodes/errorCode');
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $ec) {
            if (!$ec instanceof \DOMNode) {
                continue;
            }
            $errors[] = [
                'code'    => $this->textOf($xpath, 'code',    $ec),
                'message' => $this->textOf($xpath, 'message', $ec),
            ];
        }
        return $errors;
    }

    public function shipmentNo(\DOMXPath $xpath): string
    {
        return $this->textOf($xpath, '//shipmentNo');
    }

    /** @return list<array{code: string, date: string, time: string, description: string, location: string}> */
    public function events(\DOMXPath $xpath): array
    {
        $events   = [];
        $shipments = $xpath->query('//shipments/shipment');
        if ($shipments === false) {
            return [];
        }
        foreach ($shipments as $shipment) {
            if (!$shipment instanceof \DOMNode) {
                continue;
            }
            $eventNodes = $xpath->query('events/event', $shipment);
            if ($eventNodes === false) {
                continue;
            }
            foreach ($eventNodes as $event) {
                if (!$event instanceof \DOMNode) {
                    continue;
                }
                $events[] = [
                    'code'        => $this->textOf($xpath, 'code',        $event),
                    'date'        => $this->textOf($xpath, 'date',        $event),
                    'time'        => $this->textOf($xpath, 'time',        $event),
                    'description' => $this->textOf($xpath, 'description', $event),
                    'location'    => $this->textOf($xpath, 'location',    $event),
                ];
            }
        }
        return $events;
    }

    public function documentBase64(\DOMXPath $xpath): string
    {
        return $this->textOf($xpath, '//document');
    }

    /**
     * Colli numbers live one level deeper than the <colliNo> wrapper: the wrapper is
     * an ArrayOfColli holding <colli><colliNo> leaves (spec 5.4). Reading the wrapper
     * itself concatenates every child into one run-together string.
     *
     * @return string[]
     */
    public function colliNumbers(\DOMXPath $xpath): array
    {
        $numbers = [];
        $nodes   = $xpath->query('//shipments/shipment/colliNo/colli/colliNo');
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNode) {
                continue;
            }
            $val = trim($node->textContent);
            if ($val !== '') {
                $numbers[] = $val;
            }
        }
        return $numbers;
    }

    /**
     * Parse delivery points from a getDeliveryPoints response.
     *
     * @return list<array{symbol:string, name:string, country:string, postCode:string, city:string, street:string, streetNo:string, timeFrom:string, timeTo:string}>
     */
    public function deliveryPoints(\DOMXPath $xpath): array
    {
        $points = [];
        $nodes  = $xpath->query('//deliveryPoints/deliveryPoint');
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNode) {
                continue;
            }
            $points[] = [
                'symbol'   => $this->textOf($xpath, 'symbol',   $node),
                'name'     => $this->textOf($xpath, 'name',     $node),
                'country'  => $this->textOf($xpath, 'country',  $node),
                'postCode' => $this->textOf($xpath, 'postCode', $node),
                'city'     => $this->textOf($xpath, 'city',     $node),
                'street'   => $this->textOf($xpath, 'street',   $node),
                'streetNo' => $this->textOf($xpath, 'streetNo', $node),
                'timeFrom' => $this->textOf($xpath, 'timeFrom', $node),
                'timeTo'   => $this->textOf($xpath, 'timeTo',   $node),
            ];
        }
        return $points;
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    private function textOf(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): string
    {
        $list = $context !== null ? $xpath->query($query, $context) : $xpath->query($query);
        if ($list === false || $list->length === 0) {
            return '';
        }
        $item = $list->item(0);
        if ($item === null || !$item instanceof \DOMNode) {
            return '';
        }
        return trim($item->textContent);
    }
}
