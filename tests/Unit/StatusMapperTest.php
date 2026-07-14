<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Internal\Mapper\StatusMapper;
use VeryCodeCom\Suus\Enum\ShipmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusMapperTest extends TestCase
{
    private StatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new StatusMapper();
    }

    /** @dataProvider knownCodeProvider */
    #[DataProvider('knownCodeProvider')]
    public function testKnownCodesMapCorrectly(string $code, ShipmentStatus $expected): void
    {
        $this->assertSame($expected, $this->mapper->map($code));
    }

    public static function knownCodeProvider(): array
    {
        return [
            'J_CR -> Created'   => ['J_CR',  ShipmentStatus::Created],
            'KOL -> Created'    => ['KOL',   ShipmentStatus::Created],
            'M_KOL -> Created'  => ['M_KOL', ShipmentStatus::Created],
            'LOAD -> InTransit' => ['LOAD',  ShipmentStatus::InTransit],
            'ZALF -> InTransit' => ['ZALF',  ShipmentStatus::InTransit],
            'ZAL -> InTransit'  => ['ZAL',   ShipmentStatus::InTransit],
            'M_DYS -> InTransit'=> ['M_DYS', ShipmentStatus::InTransit],
            'WTRF -> InTransit' => ['WTRF',  ShipmentStatus::InTransit],
            'ROZF -> Delivered' => ['ROZF',  ShipmentStatus::Delivered],
            'UNDI -> Delivered' => ['UNDI',  ShipmentStatus::Delivered],
            'UNLO -> Delivered' => ['UNLO',  ShipmentStatus::Delivered],
            'ANUL -> Cancelled' => ['ANUL',  ShipmentStatus::Cancelled],
            'ZWRON -> Failed'   => ['ZWRON', ShipmentStatus::Failed],
            'ZTF -> Failed'     => ['ZTF',   ShipmentStatus::Failed],
        ];
    }

    public function testUnknownCodeDefaultsToCreated(): void
    {
        $this->assertSame(ShipmentStatus::Created, $this->mapper->map('UNKNOWN_XYZ'));
    }

    public function testIsKnownReturnsTrueForMappedCode(): void
    {
        $this->assertTrue($this->mapper->isKnown('UNDI'));
    }

    public function testIsKnownReturnsFalseForUnknownCode(): void
    {
        $this->assertFalse($this->mapper->isKnown('BANANA'));
    }

    public function testResolveFromEventsReturnsDeliveredWhenMixed(): void
    {
        $events = [
            ['code' => 'J_CR'],
            ['code' => 'LOAD'],
            ['code' => 'UNDI'],
        ];
        $this->assertSame(ShipmentStatus::Delivered, $this->mapper->resolveFromEvents($events));
    }

    public function testResolveFromEventsReturnsInTransitWhenNotDelivered(): void
    {
        $events = [
            ['code' => 'J_CR'],
            ['code' => 'LOAD'],
        ];
        $this->assertSame(ShipmentStatus::InTransit, $this->mapper->resolveFromEvents($events));
    }

    public function testResolveFromEventsReturnsPendingForEmptyList(): void
    {
        $this->assertSame(ShipmentStatus::Pending, $this->mapper->resolveFromEvents([]));
    }

    public function testResolveFromEventsSkipsUnknownCodes(): void
    {
        $events = [
            ['code' => 'UNKNOWN'],
            ['code' => 'J_CR'],
        ];
        $this->assertSame(ShipmentStatus::Created, $this->mapper->resolveFromEvents($events));
    }
}
