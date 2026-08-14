<?php

/**
 * Example: Using country calendars for business-day calculations.
 * Run: php examples/06_calendar.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\Calendar\AustriaCalendar;
use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Calendar\CzechCalendar;
use VeryCodeCom\Suus\Calendar\GermanCalendar;
use VeryCodeCom\Suus\Calendar\HungarianCalendar;
use VeryCodeCom\Suus\Calendar\PolishCalendar;
use VeryCodeCom\Suus\Calendar\RomanianCalendar;
use VeryCodeCom\Suus\Calendar\SlovakCalendar;
use VeryCodeCom\Suus\Calendar\SlovenianCalendar;
use VeryCodeCom\Suus\Calendar\SwitzerlandCalendar;

$calendars = [
    'PL' => new PolishCalendar(),
    'DE' => new GermanCalendar(),
    'AT' => new AustriaCalendar(),
    'CH' => new SwitzerlandCalendar(),
    'CZ' => new CzechCalendar(),
    'SK' => new SlovakCalendar(),
    'HU' => new HungarianCalendar(),
    'RO' => new RomanianCalendar(),
    'SI' => new SlovenianCalendar(),
];

// Check key dates across all calendars
$dates = [
    '2025-04-18' => 'Good Friday (DE/CH/CZ/SK/SI holiday, not PL/AT/HU)',
    '2025-04-21' => 'Easter Monday (holiday everywhere, Western)',
    '2025-05-01' => 'Labour Day (holiday everywhere)',
    '2025-05-03' => 'Polish Constitution Day (PL only, Sat in 2025)',
    '2025-06-19' => 'Corpus Christi (PL/AT) / Ger. not federal',
    '2025-08-01' => 'Swiss National Day (CH only)',
    '2025-08-15' => 'Assumption (PL/AT/RO - but check RO uses Orthodox!)',
    '2025-08-20' => 'Hungarian Foundation Day (HU only)',
    '2025-10-03' => 'German Unity Day (DE only)',
    '2025-10-23' => 'Hungarian Revolution Day (HU only)',
    '2025-10-26' => 'Austrian National Day (AT only)',
    '2025-10-31' => 'Reformation Day (SI only)',
    '2025-12-01' => 'Romanian National Day (RO only)',
    '2024-05-05' => 'Orthodox Easter Sunday 2024 (RO only, Western Easter = March 31)',
    '2024-05-06' => 'Orthodox Easter Monday 2024 (RO only)',
];

echo str_pad('Date', 12) . str_pad('Description', 48) . implode(' ', array_keys($calendars)) . "\n";
echo str_repeat('-', 12 + 48 + count($calendars) * 4) . "\n";

foreach ($dates as $date => $label) {
    $dt = new DateTimeImmutable($date);
    echo str_pad($date, 12) . str_pad(substr($label, 0, 47), 48);
    foreach ($calendars as $code => $cal) {
        $business = $cal->isBusinessDay($dt) ? 'work' : 'off ';
        echo ' ' . $business;
    }
    echo "\n";
}

// CalendarFactory auto-detection
echo "\n--- CalendarFactory auto-detection ---\n";
foreach (array_keys($calendars) as $cc) {
    $cal = CalendarFactory::forCountry($cc);
    echo "  {$cc} -> " . (new \ReflectionClass($cal))->getShortName() . "\n";
}

// addBusinessDays example with Romania (Orthodox Easter 2024)
echo "\n--- Romanian calendar: Orthodox Easter 2024 ---\n";
$ro = new RomanianCalendar();
echo "Orthodox Easter 2024: " . $ro->orthodoxEasterDate(2024)->format('Y-m-d') . "\n";
echo "Orthodox Easter 2025: " . $ro->orthodoxEasterDate(2025)->format('Y-m-d') . "\n";
echo "From 2024-04-30 + 2 RO business days = ";
echo $ro->addBusinessDays(new DateTimeImmutable('2024-04-30'), 2)->format('Y-m-d');
echo " (skips Orthodox Good Friday May 3 and Orthodox Easter May 5)\n";

// Override calendar in SuusClient
echo "\n--- Override calendar in SuusClient ---\n";
echo "// Use HungarianCalendar when sender is in Hungary:\n";
echo "// \$client = new SuusClient(\$config, calendar: new HungarianCalendar());\n";
echo "// Auto-detection (no override needed - client reads sender country code):\n";
echo "// \$client = new SuusClient(\$config);\n";

// Minimum loading date (+2 business days) per country - this is exactly what
// createShipment() uses when you leave ShipmentOrder::loadingDate null.
echo "\n--- Earliest valid loading date (+2 business days from today) ---\n";
foreach ($calendars as $code => $cal) {
    $earliest = $cal->minLoadingDate();                       // default: +2 business days
    echo "  {$code}: " . $earliest->format('Y-m-d (D)') . "\n";
}

// addBusinessDays / isBusinessDay work standalone for your own scheduling too.
echo "\n--- Standalone scheduling helpers (PL) ---\n";
$pl    = new PolishCalendar();
$today = new DateTimeImmutable('today');
echo "  today is a PL business day? " . ($pl->isBusinessDay($today) ? 'yes' : 'no') . "\n";
echo "  today + 5 PL business days = " . $pl->addBusinessDays($today, 5)->format('Y-m-d (D)') . "\n";

