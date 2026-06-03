<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Calendar\PolishCalendar;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Internal\Validator\ShipmentValidator;
use PHPUnit\Framework\TestCase;

final class ShipmentValidatorTest extends TestCase
{
    private ShipmentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShipmentValidator(new PolishCalendar());
    }

    private function makeOrder(array $overrides = []): ShipmentOrder
    {
        return new ShipmentOrder(
            reference:   $overrides['reference']   ?? 'ORDER-001',
            sender:      $overrides['sender']       ?? new Address('Sender', 'St', '1', '00-001', 'Warsaw',  'PL', phone: '+48600000'),
            receiver:    $overrides['receiver']     ?? new Address('Recv',   'St', '2', '10115', 'Berlin',  'DE', phone: '+4930000'),
            packages:    $overrides['packages']     ?? [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:   array_key_exists('incoterms', $overrides) ? $overrides['incoterms'] : Incoterm::DAP,
            loadingDate: $overrides['loadingDate']  ?? (new PolishCalendar())->addBusinessDays(new \DateTimeImmutable('today'), 5),
        );
    }

    // ──────────────────────────────────────────────
    // Valid orders
    // ──────────────────────────────────────────────

    public function testValidInternationalOrderProducesNoErrors(): void
    {
        $errors = $this->validator->validate($this->makeOrder());
        $this->assertEmpty($errors, implode('; ', $errors));
    }

    public function testValidDomesticPlOrderWithoutIncotermsProducesNoErrors(): void
    {
        $errors = $this->validator->validate($this->makeOrder([
            'receiver'  => new Address('Recv', 'St', '2', '30-002', 'Kraków', 'PL', phone: '+48500000'),
            'incoterms' => null,
        ]));
        $this->assertEmpty($errors, implode('; ', $errors));
    }

    // ──────────────────────────────────────────────
    // Incoterms
    // ──────────────────────────────────────────────

    public function testMissingIncotermsForInternationalRouteIsAnError(): void
    {
        $errors = $this->validator->validate($this->makeOrder(['incoterms' => null]));

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains(strtolower($e), 'incoterms')),
            'Expected an incoterms error, got: ' . implode('; ', $errors),
        );
    }

    // ──────────────────────────────────────────────
    // Loading date
    // ──────────────────────────────────────────────

    public function testLoadingDateInPastIsAnError(): void
    {
        $errors = $this->validator->validate($this->makeOrder([
            'loadingDate' => new \DateTimeImmutable('-1 day'),
        ]));
        $this->assertNotEmpty($errors);
    }

    public function testLoadingDateTomorrowIsTooEarly(): void
    {
        // SUUS requires minimum +2 Polish business days
        $errors = $this->validator->validate($this->makeOrder([
            'loadingDate' => new \DateTimeImmutable('+1 day'),
        ]));
        $this->assertNotEmpty($errors);
    }

    // ──────────────────────────────────────────────
    // Packages - limits
    // ──────────────────────────────────────────────

    public function testTooManyPackagesIsAnError(): void
    {
        // SUUS limit: 124 packages per order
        $packages = array_fill(0, 125, new Package(PackageSymbol::KAR, weightKg: 1.0));
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, '124') || str_contains(strtolower($e), 'package')),
            'Expected a package count error, got: ' . implode('; ', $errors),
        );
    }

    public function testEmptyPackageListIsRejectedByShipmentOrder(): void
    {
        // ShipmentOrder enforces at least one package at construction time.
        $this->expectException(\InvalidArgumentException::class);

        $this->makeOrder(['packages' => []]);
    }

    public function testSinglePackageWithValidWeightIsOk(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 31.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));
        $this->assertEmpty($errors, implode('; ', $errors));
    }

    // ──────────────────────────────────────────────
    // Packages - weight limits
    // ──────────────────────────────────────────────

    public function testPackageOverMaxWeightIsAnError(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 127.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'weightKg') || str_contains($e, '126')),
            'Expected a per-package weight error, got: ' . implode('; ', $errors),
        );
    }

    public function testPackageAtMaxWeightIsOk(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 126.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));
        $weightErrors = array_filter($errors, fn($e) => str_contains($e, 'weightKg'));
        $this->assertEmpty($weightErrors, implode('; ', $errors));
    }

    public function testTotalWeightExceedingLimitIsAnError(): void
    {
        // 7 × 126 kg = 882 kg > 800 kg limit
        $packages = array_fill(0, 7, new Package(PackageSymbol::EUR, weightKg: 126.0));
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'Total weight') || str_contains($e, '800')),
            'Expected a total weight error, got: ' . implode('; ', $errors),
        );
    }

    // ──────────────────────────────────────────────
    // Packages - dimension limits
    // ──────────────────────────────────────────────

    public function testPackageLengthExceedingLimitIsAnError(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 241.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'lengthCm') || str_contains($e, '240')),
            'Expected a lengthCm error, got: ' . implode('; ', $errors),
        );
    }

    public function testPackageWidthExceedingLimitIsAnError(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 10.0, widthCm: 121.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'widthCm') || str_contains($e, '120')),
            'Expected a widthCm error, got: ' . implode('; ', $errors),
        );
    }

    public function testPackageHeightExceedingLimitIsAnError(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 10.0, heightCm: 221.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));

        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'heightCm') || str_contains($e, '220')),
            'Expected a heightCm error, got: ' . implode('; ', $errors),
        );
    }

    public function testPackageAtMaxDimensionsIsOk(): void
    {
        $packages = [new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 240.0, widthCm: 120.0, heightCm: 220.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));
        $dimErrors = array_filter($errors, fn($e) => str_contains($e, 'Cm'));
        $this->assertEmpty($dimErrors, implode('; ', $errors));
    }

    public function testNullDimensionsAreIgnored(): void
    {
        // Dimensions are optional - null should produce no dimension errors.
        $packages = [new Package(PackageSymbol::KAR, weightKg: 10.0)];
        $errors   = $this->validator->validate($this->makeOrder(['packages' => $packages]));
        $dimErrors = array_filter($errors, fn($e) => str_contains($e, 'Cm'));
        $this->assertEmpty($dimErrors, implode('; ', $errors));
    }

    // ──────────────────────────────────────────────
    // Loading date - business day check
    // ──────────────────────────────────────────────

    public function testLoadingDateOnWeekendIsAnError(): void
    {
        // 2025-06-07 is a Saturday
        $errors = $this->validator->validate($this->makeOrder([
            'loadingDate' => new \DateTimeImmutable('2025-06-07'),
        ]));
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains(strtolower($e), 'business day')),
            'Expected a business day error, got: ' . implode('; ', $errors),
        );
    }

    public function testNullLoadingDateIsAlwaysValid(): void
    {
        // null means auto-computed, skip validation
        $order  = $this->makeOrder(['loadingDate' => null]);
        $errors = $this->validator->validate($order);
        $dateErrors = array_filter($errors, fn($e) => str_contains($e, 'loadingDate'));
        $this->assertEmpty($dateErrors, implode('; ', $errors));
    }

    // ──────────────────────────────────────────────
    // Address - mobilePhone length
    // ──────────────────────────────────────────────

    public function testSenderMobilePhoneTooLongIsAnError(): void
    {
        $longPhone = str_repeat('9', 31);
        $order = new ShipmentOrder(
            reference:   'ORDER-001',
            sender:      new Address('Sender', 'St', '1', '00-001', 'Warsaw', 'PL', mobilePhone: $longPhone),
            receiver:    new Address('Recv',   'St', '2', '10115',  'Berlin', 'DE', phone: '+4930000'),
            packages:    [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:   Incoterm::DAP,
            loadingDate: (new PolishCalendar())->addBusinessDays(new \DateTimeImmutable('today'), 5),
        );

        $errors = $this->validator->validate($order);
        $this->assertTrue(
            (bool) array_filter($errors, fn($e) => str_contains($e, 'mobilePhone') && str_contains($e, '30')),
            'Expected a mobilePhone length error, got: ' . implode('; ', $errors),
        );
    }
}
