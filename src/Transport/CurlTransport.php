<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Transport;

use VeryCodeCom\Suus\Exception\SuusTransportException;

/**
 * Default cURL-based SOAP transport.
 *
 * No external dependencies - uses only the PHP ext-curl extension.
 * SSL verification is disabled automatically for sandbox endpoints
 * via TransportRequest::$verifySsl.
 */
final class CurlTransport implements TransportInterface
{
    public function send(TransportRequest $request): TransportResponse
    {
        $ch = curl_init($request->endpoint);

        if ($ch === false) {
            throw new SuusTransportException('curl_init() failed - is ext-curl installed?');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request->body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "#' . $request->soapAction . '"',
            ],
            CURLOPT_SSL_VERIFYPEER => $request->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $request->verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT        => $request->timeout,
            CURLOPT_CONNECTTIMEOUT => $request->connectTimeout,
        ]);

        $body    = curl_exec($ch);
        $errNo   = curl_errno($ch);
        $errMsg  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0 || $body === false) {
            throw new SuusTransportException(
                "cURL error #{$errNo} calling SUUS ({$request->soapAction}): {$errMsg}"
            );
        }

        return new TransportResponse($httpCode, (string) $body);
    }
}
