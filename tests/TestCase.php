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

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Resolves the stress-test iteration budget.
     *
     * The value is read from OMEGA_STRESS_ITERATIONS at call time so that the
     * env vars declared in phpunit.xml.dist (applied only after the Pest
     * bootstrap has run) are honoured:
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
