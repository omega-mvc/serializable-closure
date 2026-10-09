<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

namespace Tests;

use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Resets the process-global serializer state between tests.
     *
     * Signed::$signer, Native::$transformUseVariables and
     * Native::$resolveUseVariables are static and would otherwise leak from one
     * test to the next (the Pest expectations used to catch their exceptions,
     * letting the trailing clean-up statements run).
     */
    protected function tearDown(): void
    {
        Signed::$signer = null;
        Native::$transformUseVariables = null;
        Native::$resolveUseVariables = null;

        parent::tearDown();
    }

    /**
     * Asserts that the given callback throws an exception of the expected class.
     *
     * PHPUnit has no core assertThrows(); this keeps multi-throw assertions
     * expressible inside a single test method.
     *
     * @param class-string<\Throwable> $expectedException Holds the expected exception class.
     * @param callable(): mixed        $callback          Holds the callback that must throw.
     * @return void
     */
    protected function assertThrows(string $expectedException, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf($expectedException, $throwable);

            return;
        }

        $this->fail(sprintf('Expected %s to be thrown.', $expectedException));
    }

    /**
     * Resolves the stress-test iteration budget.
     *
     * The value is read from OMEGA_STRESS_ITERATIONS at call time so that the
     * env vars declared in phpunit.xml.dist are honoured:
     *
     *   • OMEGA_STRESS_ITERATIONS set (positive int) → value as-is
     *   • OMEGA_TEST_MODE = "light"                →      10
     *   • CI / GITHUB_ACTIONS set                  →     100
     *   • Local development                        →  10 000
     *
     * @return int The number of stress iterations to run.
     */
    public static function stressIterations(): int
    {
        $value = getenv('OMEGA_STRESS_ITERATIONS');

        if ($value !== false && (int) $value > 0) {
            return (int) $value;
        }

        if (getenv('OMEGA_TEST_MODE') === 'light') {
            return 10;
        }

        if (getenv('CI') !== false || getenv('GITHUB_ACTIONS') !== false) {
            return 100;
        }

        return 10_000;
    }
}
