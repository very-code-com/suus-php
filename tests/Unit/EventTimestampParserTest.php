<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Internal\Mapper\EventTimestampParser;

final class EventTimestampParserTest extends TestCase
{
    private EventTimestampParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EventTimestampParser();
    }

    #[DataProvider('validTimestamps')]
    public function testParsesValidSuusTimestamps(
        string $date,
        string $time,
        string $format,
        string $expected,
    ): void {
        $parsed = $this->parser->parse($date, $time);

        $this->assertNotNull($parsed);
        $this->assertSame($expected, $parsed->format($format));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function validTimestamps(): iterable
    {
        yield 'whole seconds' => [
            '2026-08-19', '10:04:14',
            'Y-m-d H:i:s.u', '2026-08-19 10:04:14.000000',
        ];
        yield 'fractional seconds from a real J_CR event' => [
            '2026-08-19', '10:04:13.776667',
            'Y-m-d H:i:s.u', '2026-08-19 10:04:13.776667',
        ];
        yield 'fraction shorter than PHP microseconds' => [
            '2026-08-19', '10:04:13.7',
            'Y-m-d H:i:s.u', '2026-08-19 10:04:13.700000',
        ];
        yield 'fraction longer than PHP microseconds' => [
            '2026-08-19', '10:04:13.776667999',
            'Y-m-d H:i:s.u', '2026-08-19 10:04:13.776667',
        ];
        yield 'UTC timezone' => [
            '2026-08-19', '10:04:13.776667Z',
            'Y-m-d H:i:s.uP', '2026-08-19 10:04:13.776667+00:00',
        ];
        yield 'positive timezone offset' => [
            '2026-08-19', '10:04:13+02:00',
            'Y-m-d H:i:sP', '2026-08-19 10:04:13+02:00',
        ];
        yield 'date without time defaults to midnight' => [
            '2026-08-19', '',
            'Y-m-d H:i:s.u', '2026-08-19 00:00:00.000000',
        ];
        yield 'surrounding whitespace' => [
            ' 2026-08-19 ', ' 10:04:13.776667 ',
            'Y-m-d H:i:s.u', '2026-08-19 10:04:13.776667',
        ];
    }

    #[DataProvider('invalidTimestamps')]
    public function testReturnsNullInsteadOfInventingTimestamp(string $date, string $time): void
    {
        $this->assertNull($this->parser->parse($date, $time));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidTimestamps(): iterable
    {
        yield 'no date or time' => ['', ''];
        yield 'time without date' => ['', '10:04:13'];
        yield 'nonsense' => ['not a date', 'nonsense'];
        yield 'invalid calendar date' => ['2026-02-30', '10:04:13'];
        yield 'invalid clock time' => ['2026-08-19', '25:04:13'];
        yield 'fraction without digits' => ['2026-08-19', '10:04:13.'];
        yield 'invalid timezone offset' => ['2026-08-19', '10:04:13+15:00'];
    }
}
