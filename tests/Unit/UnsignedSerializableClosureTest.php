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

use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\UnsignedSerializableClosure;

test('it invokes the wrapped closure with forwarded arguments', function () {
    $unsigned = new UnsignedSerializableClosure(fn (int $a, int $b): int => $a * $b);

    expect($unsigned(6, 7))->toBe(42)
        ->and($unsigned->getClosure())->toBeInstanceOf(Closure::class);
});

test('its payload carries only the native serializable', function () {
    $unsigned = new UnsignedSerializableClosure(fn (): string => 'x');

    $data = $unsigned->__serialize();

    expect(array_keys($data))->toBe(['serializable'])
        ->and($data['serializable'])->toBeInstanceOf(Native::class);
});

test('it survives a full serialization cycle', function () {
    $factor = 3;
    $restored = unserialize(serialize(new UnsignedSerializableClosure(fn (int $x): int => $x * $factor)));

    expect($restored)->toBeInstanceOf(UnsignedSerializableClosure::class)
        ->and($restored(5))->toBe(15);
});
