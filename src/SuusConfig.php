<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus;

/**
 * SUUS API client configuration.
 *
 * Build it with a named constructor, from the environment, or from an array:
 *
 *   $config = SuusConfig::sandbox('ws_login', 'secret');
 *   $config = SuusConfig::production('ws_login', 'secret');
 *   $config = SuusConfig::fromEnv();
 *   $config = SuusConfig::fromArray([
 *       'login'           => 'ws_login',
 *       'password'        => 'secret',
 *       'env'             => 'sandbox',       // or 'production'
 *       'timeout'         => 30,
 *       'connect_timeout' => 10,
 *       'debug'           => false,
 *   ]);
 *
 * See {@see self::fromEnv()} for the environment variables that are read.
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
        /**
         * When true, the client attaches the raw SUUS response to thrown
         * exceptions and logs a full debug report (raw XML + stack trace) for
         * every error. Meant for diagnosing unrecognised errors; leave it off in
         * production to keep exceptions and logs concise.
         */
        public readonly bool   $debug = false,
    ) {
        if (trim($login) === '') {
            throw new \InvalidArgumentException('SuusConfig: login must not be empty.');
        }
        if (trim($password) === '') {
            throw new \InvalidArgumentException('SuusConfig: password must not be empty.');
        }
    }

    // -- Named constructors ----------------------------------------------------

    public static function sandbox(string $login, string $password): self
    {
        return new self($login, $password, sandbox: true);
    }

    public static function production(string $login, string $password): self
    {
        return new self($login, $password, sandbox: false);
    }

    // -- Environment-variable factory ------------------------------------------

    /**
     * Build config from environment variables.
     *
     * Required: SUUS_LOGIN, SUUS_PASSWORD
     * Optional: SUUS_ENV (sandbox|production, default: production)
     *           SUUS_TIMEOUT (int, default: 30)
     *           SUUS_CONNECT_TIMEOUT (int, default: 10)
     *           SUUS_DEBUG (1|true|yes|on, default: off)
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
        $debug    = in_array(strtolower($get('SUUS_DEBUG')), ['1', 'true', 'yes', 'on'], true);

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
            debug:          $debug,
        );
    }

    // -- Array factory ---------------------------------------------------------

    /**
     * Build config from an associative array.
     *
     * Keys: login*, password*, env (sandbox|production), timeout, connect_timeout, debug
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
        $debug    = (bool) ($config['debug']             ?? false);

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
            debug:          $debug,
        );
    }

    // -- Helpers ---------------------------------------------------------------

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
