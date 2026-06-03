<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\SuusConfig;

class SuusConfigTest extends TestCase
{
    // ── fromEnv() ─────────────────────────────────────────────────────────────

    public function testFromEnvProductionByDefault(): void
    {
        putenv('SUUS_LOGIN=ws_test');
        putenv('SUUS_PASSWORD=secret');
        putenv('SUUS_ENV=');

        $config = SuusConfig::fromEnv();

        self::assertSame('ws_test', $config->login);
        self::assertSame('secret', $config->password);
        self::assertFalse($config->sandbox);
        self::assertSame('production', $config->getEnvironment());
        self::assertSame(SuusConfig::ENDPOINT_PRODUCTION, $config->getEndpoint());
        self::assertTrue($config->verifySsl());

        putenv('SUUS_LOGIN');
        putenv('SUUS_PASSWORD');
    }

    public function testFromEnvSandbox(): void
    {
        putenv('SUUS_LOGIN=ws_sandbox');
        putenv('SUUS_PASSWORD=pass');
        putenv('SUUS_ENV=sandbox');

        $config = SuusConfig::fromEnv();

        self::assertTrue($config->sandbox);
        self::assertSame('sandbox', $config->getEnvironment());
        self::assertSame(SuusConfig::ENDPOINT_SANDBOX, $config->getEndpoint());
        self::assertFalse($config->verifySsl());

        putenv('SUUS_LOGIN');
        putenv('SUUS_PASSWORD');
        putenv('SUUS_ENV');
    }

    public function testFromEnvCustomTimeouts(): void
    {
        putenv('SUUS_LOGIN=ws_test');
        putenv('SUUS_PASSWORD=secret');
        putenv('SUUS_TIMEOUT=60');
        putenv('SUUS_CONNECT_TIMEOUT=15');

        $config = SuusConfig::fromEnv();

        self::assertSame(60, $config->timeout);
        self::assertSame(15, $config->connectTimeout);

        putenv('SUUS_LOGIN');
        putenv('SUUS_PASSWORD');
        putenv('SUUS_TIMEOUT');
        putenv('SUUS_CONNECT_TIMEOUT');
    }

    public function testFromEnvThrowsWhenLoginMissing(): void
    {
        putenv('SUUS_LOGIN=');
        putenv('SUUS_PASSWORD=secret');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SUUS_LOGIN/');

        SuusConfig::fromEnv();

        putenv('SUUS_PASSWORD');
    }

    public function testFromEnvThrowsWhenPasswordMissing(): void
    {
        putenv('SUUS_LOGIN=ws_test');
        putenv('SUUS_PASSWORD=');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SUUS_PASSWORD/');

        SuusConfig::fromEnv();

        putenv('SUUS_LOGIN');
    }

    // ── fromArray() ───────────────────────────────────────────────────────────

    public function testFromArrayProduction(): void
    {
        $config = SuusConfig::fromArray([
            'login'    => 'ws_login',
            'password' => 'secret',
            'env'      => 'production',
        ]);

        self::assertFalse($config->sandbox);
        self::assertSame(30, $config->timeout);
        self::assertSame(10, $config->connectTimeout);
    }

    public function testFromArraySandbox(): void
    {
        $config = SuusConfig::fromArray([
            'login'           => 'ws_login',
            'password'        => 'secret',
            'env'             => 'sandbox',
            'timeout'         => 45,
            'connect_timeout' => 5,
        ]);

        self::assertTrue($config->sandbox);
        self::assertSame(45, $config->timeout);
        self::assertSame(5, $config->connectTimeout);
    }

    public function testFromArrayThrowsWhenLoginMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"login"/');

        SuusConfig::fromArray(['password' => 'secret']);
    }

    public function testFromArrayThrowsWhenPasswordMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"password"/');

        SuusConfig::fromArray(['login' => 'ws_login']);
    }

    public function testFromArrayDefaultsToProductionWhenEnvOmitted(): void
    {
        $config = SuusConfig::fromArray(['login' => 'ws_login', 'password' => 'secret']);

        self::assertFalse($config->sandbox);
        self::assertSame('production', $config->getEnvironment());
    }

    // ── named constructors ────────────────────────────────────────────────────

    public function testSandboxNamedConstructor(): void
    {
        $config = SuusConfig::sandbox('l', 'p');
        self::assertTrue($config->sandbox);
        self::assertFalse($config->verifySsl());
    }

    public function testProductionNamedConstructor(): void
    {
        $config = SuusConfig::production('l', 'p');
        self::assertFalse($config->sandbox);
        self::assertTrue($config->verifySsl());
    }
}
