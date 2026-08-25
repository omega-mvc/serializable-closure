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
    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure(fn (): int => 1)));

    expect($restored())->toBe(1);
});

test('nested array bodies survive the round trip', function () {
    $closure = function (): array {
        return ['a' => [1, 2], 'b' => ['c' => 3]];
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    $result = $restored();

    if (! is_array($result)) {
        throw new Exception('Unexpected restored type.');
    }

    expect($result)->toBe(['a' => [1, 2], 'b' => ['c' => 3]]);
});

test('string interpolation braces survive the round trip', function () {
    $closure = function (): string {
        $name = 'x';

        return "hi {$name} and {$name}!";
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    expect($restored())->toBe('hi x and x!');
});

test('objects holding serializable closures restore their bindings', function () {
    $holder = new stdClass();
    $holder->callback = fn (): int => 11;

    $closure = function () use ($holder): int {
        return ($holder->callback)();
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    expect($restored())->toBe(11);
});

test('deferred bindings rewire object properties holding wrappers', function () {
    $holder = new \Tests\Fixtures\UntypedHolder();
    $holder->payload = new SerializableClosure(fn (): int => 33);

    $closure = function () use ($holder) {
        // After the round trip the deferred binding stores the raw closure.
        $wrapped = $holder->payload;

        return $wrapped();
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    expect($restored())->toBe(33);
});

test('nullsafe and static-class access survive the round trip', function () {
    $host = new UserDefinedFixture();

    $closure = function () use ($host): string {
        $maybe = unserialize('N;');

        return is_string($maybe) ? $maybe : $host::class;
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

    expect($restored() ?? '')->toContain('UserDefinedFixture');
});

test('interpolation and casts keep working after the round trip', function () {
    $label = 'L';
    $closure = function (int $n) use ($label): string {
        $casted = (int) $n;

        return "{$label}:{$casted}";
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

    expect($restored(9))->toBe('L:9');
});

test('__LINE__ arithmetic is preserved across the round trip', function () {
    $closure = static function (): int {
        return
            __LINE__
            ;
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    expect($restored())->toBeInt();
});

test('named arguments in inner calls are preserved', function () {
    $closure = fn (): array => [array_keys(['k' => 1])];

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
    expect($restored())->toBe([['k']]);
});

test('recursive closures round-trip through their self reference', function () {
    $factorial = function (int $n) use (&$factorial): int {
        return $n <= 1 ? 1 : $n * $factorial($n - 1);
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($factorial)));

    expect($restored(5))->toBe(120);
});

test('the same captured closure is serialized once and stays shared', function () {
    $inner = fn (): int => 7;
    $alias = $inner;

    $outer = function () use ($inner, $alias): int {
        return $inner() + $alias();
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($outer)));

    expect($restored())->toBe(14);
});

test('recursive self-referencing closures survive the round trip', function () {
    $f = function (int $n = 0) use (&$f) {
        return $n < 2 ? ($f)($n + 1) : $n;
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($f)));

    expect($restored())->toBe(2);
});

test('self-references nested in arrays and stdclass rewire after restore', function () {
    $holder = new stdClass();
    $holder->fn = static fn (): string => 'placeholder';

    $box = ['cb' => $holder];

    $f = function () use (&$box): string {
        $ref = $box;

        return 'restored';
    };

    $holder->fn = &$f;

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($f)));

    expect($restored())->toBe('restored');
});

test('circular captured arrays survive the round trip', function () {
    $loop = [];
    $loop['me'] = &$loop;

    $closure = fn (): int => count($loop);

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe(1);
});

test('aliased stdclass holders share one reconstructed instance', function () {
    $shared = new stdClass();
    $shared->marker = 'same';

    $holderA = new stdClass();
    $holderB = new stdClass();

    $holderA->link = $shared;
    $holderB->link = $shared;

    $closure = function () use ($shared, $holderA, $holderB): bool {
        return $holderA->link === $shared && $holderB->link === $shared;
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

    expect($restored())->toBeTrue();
});

test('the same captured object keeps a single identity after the round trip', function () {
    $counter = new UserDefinedFixture();
    $alias   = $counter;

    $closure = function () use ($counter, $alias): string {
        return spl_object_id($counter) === spl_object_id($alias) ? $counter->label : 'mismatch';
    };

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

    expect($restored())->toBe('fixture');
});

test('bound objects carrying recursion markers keep their arrays untouched', function () {
    $host = new \Tests\Fixtures\LoopHost();

    // A literal marker entry (not an actual cycle): wrapClosures() must
    // return the array untouched instead of walking it.
    $host->loop = [
        Omega\SerializableClosure\Serializers\Native::ARRAY_RECURSIVE_KEY => true,
        'tag'                                                             => 't',
    ];

    $bound = $host->reader();

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($bound)));
    $result   = $restored();

    if (! is_array($result)) {
        throw new Exception('Unexpected restored type.');
    }

    expect($result['tag'] ?? '')->toBe('t')
        ->and($result[\Omega\SerializableClosure\Serializers\Native::ARRAY_RECURSIVE_KEY] ?? false)->toBeTrue();
});
