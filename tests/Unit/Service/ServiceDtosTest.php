<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit\Service;

use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\EmailNotificationService;
use VeryCodeCom\Suus\Service\InsideDeliveryService;
use VeryCodeCom\Suus\Service\InsuranceService;
use VeryCodeCom\Suus\Service\LiftService;
use VeryCodeCom\Suus\Service\PalletTruckService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use PHPUnit\Framework\TestCase;

final class ServiceDtosTest extends TestCase
{
    // ── CodService ─────────────────────────────────────────────────────

    public function testCodServiceSymbol(): void
    {
        $svc = new CodService(500.0);
        $this->assertSame('RohligCOD', $svc->getSymbol());
    }

    public function testCodServiceFields(): void
    {
        $svc = new CodService(500.0, 'PLN');
        $fields = $svc->getSoapFields();
        $this->assertSame(500.0, $fields['decimal1']);
        $this->assertSame('PLN', $fields['varchar1']);
        $this->assertCount(2, $fields);
    }

    public function testCodServiceDefaultCurrency(): void
    {
        $svc = new CodService(100.0);
        $this->assertSame('PLN', $svc->getSoapFields()['varchar1']);
    }

    public function testCodServiceRejectsZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CodService(0.0);
    }

    public function testCodServiceRejectsAmountOverLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('15 000');
        new CodService(15_001.0);
    }

    // ── InsuranceService ───────────────────────────────────────────────

    public function testInsuranceServiceSymbol(): void
    {
        $svc = new InsuranceService(1000.0);
        $this->assertSame('RohligUbezpieczenie3', $svc->getSymbol());
    }

    public function testInsuranceServiceDefaultFields(): void
    {
        $svc    = new InsuranceService(1000.0);
        $fields = $svc->getSoapFields();

        $this->assertSame(1000.0, $fields['decimal1']);
        $this->assertSame(0.0,    $fields['decimal2']);
        $this->assertSame('PLN',  $fields['varchar1']);
        $this->assertSame(InsuranceService::GOODS_STANDARD, $fields['varchar2']);
        $this->assertFalse($fields['bool1']);
        $this->assertFalse($fields['bool2']);
        $this->assertArrayNotHasKey('int01', $fields);
    }

    public function testInsuranceServiceGoodsDeclarationIncluded(): void
    {
        $svc    = new InsuranceService(500.0, goodsDeclaration: 'DECL-999');
        $fields = $svc->getSoapFields();
        $this->assertSame('DECL-999', $fields['int01']);
    }

    public function testInsuranceServiceRejectsZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InsuranceService(0.0);
    }

    public function testInsuranceServiceRejectsInvalidGoodsType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UB_POZ');
        new InsuranceService(100.0, goodsType: 'INVALID');
    }

    // ── SmsNotificationService ─────────────────────────────────────────

    public function testSmsNotificationServiceSymbol(): void
    {
        $svc = new SmsNotificationService();
        $this->assertSame('StdAwizacjaSms', $svc->getSymbol());
    }

    public function testSmsNotificationServiceHasNoSoapFields(): void
    {
        $svc = new SmsNotificationService();
        $this->assertEmpty($svc->getSoapFields());
    }

    // ── EmailNotificationService ───────────────────────────────────────

    public function testEmailNotificationServiceSymbol(): void
    {
        $svc = new EmailNotificationService();
        $this->assertSame('RohligZatwierdzeniePowiadomienie', $svc->getSymbol());
    }

    public function testEmailNotificationServiceBothEnabled(): void
    {
        $svc    = new EmailNotificationService(notifySender: true, notifyReceiver: true);
        $fields = $svc->getSoapFields();
        $this->assertSame('1', $fields['varchar1']);
        $this->assertSame('1', $fields['varchar2']);
    }

    public function testEmailNotificationServiceOnlySender(): void
    {
        $svc    = new EmailNotificationService(notifySender: true, notifyReceiver: false);
        $fields = $svc->getSoapFields();
        $this->assertArrayHasKey('varchar1',    $fields);
        $this->assertArrayNotHasKey('varchar2', $fields);
    }

    public function testEmailNotificationServiceOnlyReceiver(): void
    {
        $svc    = new EmailNotificationService(notifySender: false, notifyReceiver: true);
        $fields = $svc->getSoapFields();
        $this->assertArrayNotHasKey('varchar1', $fields);
        $this->assertArrayHasKey('varchar2',    $fields);
    }

    // ── LiftService ────────────────────────────────────────────────────

    public function testLiftServiceSymbol(): void
    {
        $svc = new LiftService();
        $this->assertSame('RohligWinda', $svc->getSymbol());
    }

    public function testLiftServiceHasBool1True(): void
    {
        $svc = new LiftService();
        $this->assertTrue($svc->getSoapFields()['bool1']);
    }

    // ── InsideDeliveryService ──────────────────────────────────────────

    public function testInsideDeliveryServiceSymbol(): void
    {
        $svc = new InsideDeliveryService();
        $this->assertSame('StdWniesienie2', $svc->getSymbol());
    }

    public function testInsideDeliveryServiceHasNoSoapFields(): void
    {
        $svc = new InsideDeliveryService();
        $this->assertEmpty($svc->getSoapFields());
    }

    // ── PalletTruckService ─────────────────────────────────────────────

    public function testPalletTruckServiceSymbol(): void
    {
        $svc = new PalletTruckService();
        $this->assertSame('StdPaleciak', $svc->getSymbol());
    }

    public function testPalletTruckServiceHasBool1True(): void
    {
        $svc = new PalletTruckService();
        $this->assertTrue($svc->getSoapFields()['bool1']);
    }
}
