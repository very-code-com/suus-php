<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Enum\PackageSymbol;

/**
 * Constructor-guard tests for the value objects Address and Package.
 */
final class DtoValidationTest extends TestCase
{
    // -- Address --------------------------------------------------------

    public function testValidAddressIsAccepted(): void
    {
        $addr = new Address('Acme', 'Main', '1', '00-001', 'Warsaw', 'pl', phone: '+48600');
        // getCountryCode() normalises to upper-case.
        $this->assertSame('PL', $addr->getCountryCode());
    }

    public function testAddressRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/name/');
        new Address('   ', 'Main', '1', '00-001', 'Warsaw', 'PL', phone: '+48600');
    }

    public function testAddressRejectsEmptyCity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/city/');
        new Address('Acme', 'Main', '1', '00-001', '  ', 'PL', phone: '+48600');
    }

    public function testAddressRejectsEmptyPostcode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/postcode/');
        new Address('Acme', 'Main', '1', '', 'Warsaw', 'PL', phone: '+48600');
    }

    public function testAddressRejectsInvalidCountryCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/2-letter/');
        new Address('Acme', 'Main', '1', '00-001', 'Warsaw', 'POL', phone: '+48600');
    }

    public function testAddressRejectsMissingBothPhones(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/phone/');
        new Address('Acme', 'Main', '1', '00-001', 'Warsaw', 'PL');
    }

    public function testAddressAcceptsMobilePhoneOnly(): void
    {
        $addr = new Address('Acme', 'Main', '1', '00-001', 'Warsaw', 'PL', mobilePhone: '+48600');
        $this->assertSame('+48600', $addr->mobilePhone);
        $this->assertNull($addr->phone);
    }

    // -- Package --------------------------------------------------------

    public function testValidPackageIsAccepted(): void
    {
        $pkg = new Package(PackageSymbol::KAR, weightKg: 10.0);
        $this->assertSame(10.0, $pkg->weightKg);
    }

    public function testPackageRejectsZeroWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/weightKg/');
        new Package(PackageSymbol::KAR, weightKg: 0.0);
    }

    public function testPackageRejectsNegativeWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/weightKg/');
        new Package(PackageSymbol::KAR, weightKg: -5.0);
    }
}
