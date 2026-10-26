<?php

declare(strict_types=1);

/**
 * Runs the full automated test suite. Usage: php tests/run.php
 *
 * Every test file in this directory matching *Test.php returns a
 * callable; run.php requires each one and invokes it in turn. See
 * TestCase.php for why this is a small hand-written harness and not
 * PHPUnit.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestCase.php';

T::init();

$testFiles = glob(__DIR__ . '/*Test.php');
sort($testFiles);

echo "Farm ERP automated test suite\n";
echo 'Database driver under test: ' . App\Core\Database::driver() . "\n";
echo str_repeat('=', 60) . "\n";

foreach ($testFiles as $file) {
    $testGroup = require $file;
    $testGroup();
    echo "\n";
}

$result = T::result();
echo str_repeat('=', 60) . "\n";
echo "Passed: {$result->passed}\n";
echo "Failed: {$result->failed}\n";

if ($result->failed > 0) {
    echo "\nFailures:\n";
    foreach ($result->failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
