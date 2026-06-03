<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Dto\StatusResult;
use VeryCodeCom\Suus\Enum\ShipmentStatus;

final class StatusResultTest extends TestCase
{
    private function makeResult(ShipmentStatus $status): StatusResult
    {
        return new StatusResult($status, '', []);
    }

    // ── isDelivered ──────────────────────────────────────────────────

    public function testIsDeliveredReturnsTrueForDelivered(): void
    {
        $this->assertTrue($this->makeResult(ShipmentStatus::Delivered)->isDelivered());
    }

    public function testIsDeliveredReturnsFalseForInTransit(): void
    {
        $this->assertFalse($this->makeResult(ShipmentStatus::InTransit)->isDelivered());
    }

    public function testIsDeliveredReturnsFalseForCancelled(): void
    {
        $this->assertFalse($this->makeResult(ShipmentStatus::Cancelled)->isDelivered());
    }

    // ── isFinal ──────────────────────────────────────────────────────

    public function testIsFinalTrueForDelivered(): void
    {
        $this->assertTrue($this->makeResult(ShipmentStatus::Delivered)->isFinal());
    }

    public function testIsFinalTrueForCancelled(): void
    {
        $this->assertTrue($this->makeResult(ShipmentStatus::Cancelled)->isFinal());
    }

    public function testIsFinalTrueForFailed(): void
    {
        $this->assertTrue($this->makeResult(ShipmentStatus::Failed)->isFinal());
    }

    public function testIsFinalFalseForCreated(): void
    {
        $this->assertFalse($this->makeResult(ShipmentStatus::Created)->isFinal());
    }

    public function testIsFinalFalseForInTransit(): void
    {
        $this->assertFalse($this->makeResult(ShipmentStatus::InTransit)->isFinal());
    }

    public function testIsFinalFalseForPending(): void
    {
        $this->assertFalse($this->makeResult(ShipmentStatus::Pending)->isFinal());
    }
}
