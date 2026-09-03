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

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Stress-test iteration budget
|--------------------------------------------------------------------------
|
| When set to a numeric value via the OMEGA_STRESS_ITERATIONS env var the
| value is used as-is.  Otherwise the constant is chosen automatically:
|
|   • OMEGA_TEST_MODE = "light"  →     10 iterations
|   • CI / GITHUB_ACTIONS set    →    100 iterations
|   • Local development          → 10 000 iterations
|
| Stress tests that rely on this constant are skipped automatically when the
| value is ≤ 1 (i.e. no stress budget).
|
*/

if (getenv('OMEGA_STRESS_ITERATIONS') !== false && (int) getenv('OMEGA_STRESS_ITERATIONS') > 0) {
    define('STRESS_ITERATIONS', (int) getenv('OMEGA_STRESS_ITERATIONS'));
} elseif (getenv('OMEGA_TEST_MODE') === 'light') {
    define('STRESS_ITERATIONS', 10);
} elseif (getenv('CI') !== false || getenv('GITHUB_ACTIONS') !== false) {
    define('STRESS_ITERATIONS', 100);
} else {
    define('STRESS_ITERATIONS', 10_000);
}
