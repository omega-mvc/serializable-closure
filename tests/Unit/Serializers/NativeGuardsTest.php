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

namespace Tests\Unit\Serializers;

use Omega\SerializableClosure\Serializers\Native;
use ReflectionMethod;

test('mapPointers exits quietly without an active scope', function () {
    $native = new Native(fn () => true);

    $method   = new ReflectionMethod(Native::class, 'mapPointers');
    $payload  = ['kept' => 1];
    $deferred = [];

    $bound = [&$payload, 'self-hash', &$deferred];

    $method->invokeArgs($native, $bound);

    expect($payload)->toBe(['kept' => 1])
        ->and($deferred)->toBe([]);
});

test('mapByReference exits quietly without an active scope', function () {
    $native = new Native(fn () => true);

    $method  = new ReflectionMethod(Native::class, 'mapByReference');
    $payload = ['kept' => 2];

    $bound = [&$payload];

    $method->invokeArgs($native, $bound);

    expect($payload)->toBe(['kept' => 2]);
});
