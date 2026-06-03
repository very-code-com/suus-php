<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Transport\TransportRequest;
use VeryCodeCom\Suus\Transport\TransportResponse;

final class TransportTest extends TestCase
{
    // ── TransportResponse::isSuccess ─────────────────────────────────

    public function testIsSuccessTrueFor200(): void
    {
        $this->assertTrue((new TransportResponse(200, ''))->isSuccess());
    }

    public function testIsSuccessFalseFor201(): void
    {
        $this->assertFalse((new TransportResponse(201, ''))->isSuccess());
    }

    public function testIsSuccessFalseFor404(): void
    {
        $this->assertFalse((new TransportResponse(404, ''))->isSuccess());
    }

    public function testIsSuccessFalseFor500(): void
    {
        $this->assertFalse((new TransportResponse(500, ''))->isSuccess());
    }

    public function testBodyIsAccessible(): void
    {
        $r = new TransportResponse(200, 'hello');
        $this->assertSame('hello', $r->body);
    }

    // ── TransportRequest ─────────────────────────────────────────────

    public function testTransportRequestStoresAllFields(): void
    {
        $req = new TransportRequest(
            endpoint:       'https://api.suus.com/soap',
            soapAction:     'addOrder',
            body:           '<xml/>',
            timeout:        30,
            connectTimeout: 10,
            verifySsl:      true,
        );

        $this->assertSame('https://api.suus.com/soap', $req->endpoint);
        $this->assertSame('addOrder', $req->soapAction);
        $this->assertSame('<xml/>', $req->body);
        $this->assertSame(30, $req->timeout);
        $this->assertSame(10, $req->connectTimeout);
        $this->assertTrue($req->verifySsl);
    }
}
