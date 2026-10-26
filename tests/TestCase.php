<?php

declare(strict_types=1);

/**
 * Minimal, dependency-free test harness.
 *
 * WHY NOT PHPUNIT: the master spec asks for PHPUnit. This project was
 * first built in a sandboxed environment with no outbound access to
 * Packagist (Composer's package source) or to phar.phpunit.de, so
 * `composer require --dev phpunit/phpunit` could not run and the phar
 * could not be downloaded either -- see docs/FINAL_ACCEPTANCE_REPORT.md
 * for the exact errors. This harness is a deliberately small
 * substitute with the same core idea (assert / fail a named test, print
 * a pass/fail summary, non-zero exit code on any failure) so the suite
 * below still runs as REAL, EXECUTED tests against a REAL database,
 * not a description of tests. On a machine with normal internet access,
 * running `composer require --dev phpunit/phpunit` and porting these
 * same assertions into PHPUnit TestCase classes is a mechanical,
 * low-risk follow-up -- the test bodies do not depend on anything
 * PHPUnit-specific.
 */

final class TestResult
{
    public int $passed = 0;
    public int $failed = 0;
    /** @var list<string> */
    public array $failures = [];
}

final class T
{
    private static TestResult $result;

    public static function init(): void
    {
        self::$result = new TestResult();
    }

    public static function result(): TestResult
    {
        return self::$result;
    }

    public static function run(string $name, callable $fn): void
    {
        try {
            $fn();
            self::$result->passed++;
            echo "  [PASS] {$name}\n";
        } catch (\Throwable $e) {
            self::$result->failed++;
            self::$result->failures[] = "{$name}: " . $e->getMessage();
            echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        }
    }

    public static function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Assertion failed: {$message}");
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            $e = var_export($expected, true);
            $a = var_export($actual, true);
            throw new \RuntimeException("{$message} (expected {$e}, got {$a})");
        }
    }

    public static function assertEqualsFloat(float $expected, float $actual, string $message, float $epsilon = 0.01): void
    {
        if (abs($expected - $actual) > $epsilon) {
            throw new \RuntimeException("{$message} (expected {$expected}, got {$actual})");
        }
    }

    public static function assertThrows(callable $fn, string $message): void
    {
        try {
            $fn();
        } catch (\Throwable) {
            return;
        }
        throw new \RuntimeException("Expected an exception but none was thrown: {$message}");
    }

    public static function assertNull(mixed $value, string $message): void
    {
        if ($value !== null) {
            throw new \RuntimeException("Assertion failed (expected null): {$message}");
        }
    }

    public static function assertNotNull(mixed $value, string $message): void
    {
        if ($value === null) {
            throw new \RuntimeException("Assertion failed (expected not null): {$message}");
        }
    }
}
