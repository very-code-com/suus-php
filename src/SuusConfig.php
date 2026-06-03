<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus;

/**
 * SUUS API client configuration.
 *
 * Three ways to build a config:
 *
 * 1. Named constructors (simplest):
 *      $config = SuusConfig::sandbox('ws_login', 'secret');
 *      $config = SuusConfig::production('ws_login', 'secret');
 *
 * 2. From environment variables (recommended for 12-factor apps):
 *      $config = SuusConfig::fromEnv();
 *
 *    Reads these env vars (set in .env, docker-compose, CI secrets, etc.):
 *      SUUS_LOGIN       - required
 *      SUUS_PASSWORD    - required
 *      SUUS_ENV         - "sandbox" | "production"  (default: "production")
 *      SUUS_TIMEOUT     - int seconds               (default: 30)
 *      SUUS_CONNECT_TIMEOUT - int seconds           (default: 10)
 *
 * 3. From array (useful with framework config files):
 *      $config = SuusConfig::fromArray([
 *          'login'           => 'ws_login',
 *          'password'        => 'secret',
 *          'env'             => 'sandbox',       // or 'production'
 *          'timeout'         => 30,
 *          'connect_timeout' => 10,
 *      ]);
 */
final class SuusConfig
{
    public const ENDPOINT_SANDBOX    = 'https://pkt-dev.suus.com/api/order-api/v0/ws/order';
    public const ENDPOINT_PRODUCTION = 'https://portal.suus.com/api/order-api/v0/ws/order';

    public function __construct(
        public readonly string $login,
        public readonly string $password,
        public readonly bool   $sandbox = false,
        /** Total request timeout (seconds) */
        public readonly int    $timeout = 30,
        /** TCP connection timeout (seconds) */
        public readonly int    $connectTimeout = 10,
    ) {
        if (trim($login) === '') {
            throw new \InvalidArgumentException('SuusConfig: login must not be empty.');
        }
        if (trim($password) === '') {
            throw new \InvalidArgumentException('SuusConfig: password must not be empty.');
        }
    }

    // ── Named constructors ────────────────────────────────────────────────────

    public static function sandbox(string $login, string $password): self
    {
        return new self($login, $password, sandbox: true);
    }

    public static function production(string $login, string $password): self
    {
        return new self($login, $password, sandbox: false);
    }

    // ── Environment-variable factory ──────────────────────────────────────────

    /**
     * Build config from environment variables.
     *
     * Required: SUUS_LOGIN, SUUS_PASSWORD
     * Optional: SUUS_ENV (sandbox|production, default: production)
     *           SUUS_TIMEOUT (int, default: 30)
     *           SUUS_CONNECT_TIMEOUT (int, default: 10)
     *
     * @throws \InvalidArgumentException if SUUS_LOGIN or SUUS_PASSWORD is missing.
     */
    public static function fromEnv(): self
    {
        $get = static fn(string $key): string => (string) (getenv($key) ?: ($_ENV[$key] ?? $_SERVER[$key] ?? ''));

        $login    = $get('SUUS_LOGIN');
        $password = $get('SUUS_PASSWORD');
        $env      = strtolower($get('SUUS_ENV') ?: 'production');
        $timeout  = (int) ($get('SUUS_TIMEOUT') ?: 30);
        $connect  = (int) ($get('SUUS_CONNECT_TIMEOUT') ?: 10);

        if ($login === '') {
            throw new \InvalidArgumentException('SuusConfig::fromEnv(): SUUS_LOGIN env var is not set.');
        }
        if ($password === '') {
            throw new \InvalidArgumentException('SuusConfig::fromEnv(): SUUS_PASSWORD env var is not set.');
        }

        return new self(
            login:          $login,
            password:       $password,
            sandbox:        $env === 'sandbox',
            timeout:        $timeout > 0 ? $timeout : 30,
            connectTimeout: $connect > 0 ? $connect : 10,
        );
    }

    // ── Array factory ─────────────────────────────────────────────────────────

    /**
     * Build config from an associative array.
     *
     * Keys: login*, password*, env (sandbox|production), timeout, connect_timeout
     *
     * @param array<string, mixed> $config
     * @throws \InvalidArgumentException on missing required keys.
     */
    public static function fromArray(array $config): self
    {
        $login    = (string) ($config['login']           ?? '');
        $password = (string) ($config['password']        ?? '');
        $env      = strtolower((string) ($config['env'] ?? 'production'));
        $timeout  = (int) ($config['timeout']            ?? 30);
        $connect  = (int) ($config['connect_timeout']    ?? 10);

        if ($login === '') {
            throw new \InvalidArgumentException('SuusConfig::fromArray(): "login" key is required.');
        }
        if ($password === '') {
            throw new \InvalidArgumentException('SuusConfig::fromArray(): "password" key is required.');
        }

        return new self(
            login:          $login,
            password:       $password,
            sandbox:        $env === 'sandbox',
            timeout:        $timeout > 0 ? $timeout : 30,
            connectTimeout: $connect > 0 ? $connect : 10,
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getEndpoint(): string
    {
        return $this->sandbox ? self::ENDPOINT_SANDBOX : self::ENDPOINT_PRODUCTION;
    }

    /** SSL verification is disabled for sandbox to avoid certificate issues. */
    public function verifySsl(): bool
    {
        return !$this->sandbox;
    }

    public function getEnvironment(): string
    {
        return $this->sandbox ? 'sandbox' : 'production';
    }
}
