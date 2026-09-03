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
| The iteration budget is resolved at runtime by
| Tests\TestCase::stressIterations(), which reads the OMEGA_STRESS_ITERATIONS
| env var inside test bodies — where the <env> declared in phpunit.xml.dist
| is already applied (Pest.php itself loads too early to see it).
|
| Stress tests are skipped automatically when the budget resolves to ≤ 1
| (i.e. no stress budget configured).
|
*/
