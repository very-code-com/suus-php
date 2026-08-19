<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Internal\Mapper;

/**
 * Converts the separate xsd:date / xsd:time values returned by getEvents.
 *
 * @internal
 */
final class EventTimestampParser
{
    public function parse(string $date, string $time): ?\DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $time = trim($time);
        if ($time === '') {
            $time = '00:00:00';
        }

        // xsd:time allows an arbitrary-length fractional part and an optional
        // timezone. PHP stores at most six fractional digits, so discard only
        // precision it cannot represent instead of rejecting the timestamp.
        $matched = preg_match(
            '/^(?<clock>\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d+))?'
            . '(?<timezone>Z|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))?$/D',
            $time,
            $parts,
        );
        if ($matched !== 1) {
            return null;
        }

        $format    = '!Y-m-d H:i:s';
        $timestamp = $date . ' ' . $parts['clock'];

        $fraction = $parts['fraction'] ?? '';
        if ($fraction !== '') {
            $format    .= '.u';
            $timestamp .= '.' . str_pad(substr($fraction, 0, 6), 6, '0');
        }

        $timezone = $parts['timezone'] ?? '';
        if ($timezone !== '') {
            $format    .= 'P';
            $timestamp .= $timezone === 'Z' ? '+00:00' : $timezone;
        }

        $parsed = \DateTimeImmutable::createFromFormat($format, $timestamp);
        if ($parsed === false) {
            return null;
        }

        // createFromFormat() otherwise normalizes values such as February 30th
        // and returns an object plus a warning. Such an object is invented data.
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $parsed;
    }
}
