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

use Omega\SerializableClosure\SerializableClosure;
use Tests\Fixtures\UserDefinedFixture;

test('scalar-body closures survive the round trip', function () {
    $restored = unserialize(serialize(new SerializableClosure(fn (): int => 1)));

    expect($restored())->toBe(1);
});

test('nested array bodies survive the round trip', function () {
    $closure = function (): array {
        return ['a' => [1, 2], 'b' => ['c' => 3]];
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe(['a' => [1, 2], 'b' => ['c' => 3]]);
});

test('string interpolation braces survive the round trip', function () {
    $closure = function (): string {
        $name = 'x';

        return "hi ${name} and {$name}!";
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe('hi x and x!');
});

test('objects holding serializable closures restore their bindings', function () {
    $holder = new stdClass();
    $holder->callback = fn (): int => 11;

    $closure = function () use ($holder): int {
        return ($holder->callback)();
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe(11);
});

test('deferred bindings rewire object properties holding wrappers', function () {
    $holder = new \Tests\Fixtures\UntypedHolder();
    $holder->payload = new SerializableClosure(fn (): int => 33);

    $closure = function () use ($holder): int {
        return ($holder->payload)();
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe(33);
});

test('nullsafe and static-class access survive the round trip', function () {
    $host = new UserDefinedFixture();

    $closure = function () use ($host): ?string {
        $maybe = null;

        return $maybe?->nothing ?? $host::class;
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored() ?? '')->toContain('UserDefinedFixture');
});

test('interpolation and casts keep working after the round trip', function () {
    $label = 'L';
    $closure = function (int $n) use ($label): string {
        $casted = (int) $n;

        return "{$label}:{$casted}";
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored(9))->toBe('L:9');
});

test('__LINE__ arithmetic is preserved across the round trip', function () {
    $closure = static function (): int {
        return
            __LINE__
            ;
    };

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBeInt();
});

test('named arguments in inner calls are preserved', function () {
    $closure = fn (): array => [array_keys(['k' => 1])];

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe([['k']]);
});
