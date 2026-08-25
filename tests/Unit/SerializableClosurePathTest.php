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
use Tests\Fixtures\RoundTrip;

/**
 * One combinatorial case: the raw payload handed to Native::__unserialize()
 * plus the exception the reconstruction must throw (null when it succeeds).
 *
 * @phpstan-type PathData array<string, mixed>
 * @phpstan-type PathCase array{0: PathData, 1: class-string|null}
 */
dataset('unserialize_matrix', function () {
    return [
        'happy_path_minimal' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
            ],
            null,
        ],
        // Non-string function falls back to '', the stream yields "return ;",
        // and the reconstructed value is null instead of a Closure.
        'invalid_function_type' => [
            [
                'function' => 12345,
                'this'     => null,
                'self'     => null,
            ],
            ReflectionException::class,
        ],
        'invalid_bound_type' => [
            [
                'function' => 'fn() => true',
                'this'     => ['not_an_object'],
                'self'     => 'dummy_hash',
            ],
            null,
        ],
        'invalid_self_hash_type' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => ['not_a_string'],
            ],
            null,
        ],
        'use_array_with_mixed_keys' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'use'      => [
                    'kept' => 'string_key',
                    0      => 'integer_key_dropped',
                ],
            ],
            null,
        ],
        // Only user-defined classes are valid scopes: binding to an internal
        // class is rejected by the engine, so the row uses a fixture class.
        'scope_with_valid_class' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'scope'    => RoundTrip::class,
            ],
            null,
        ],
        'scope_with_invalid_class' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'scope'    => 'NoSuchClassAnywhere',
            ],
            null,
        ],
        'scope_with_invalid_type' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'scope'    => ['array_instead_of_string'],
            ],
            null,
        ],
        // The stream wraps code as "<?php\nreturn {code};", so a scalar body
        // includes cleanly and hits the reconstructed-value-is-not-a-Closure guard.
        'reconstructed_is_not_a_closure' => [
            [
                'function' => '42',
                'this'     => null,
                'self'     => 'dummy_hash',
            ],
            ReflectionException::class,
        ],
        // A forged recursion marker as a use entry is skipped by mapPointers().
        'marker_key_at_use_top_level' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'use'      => [
                    Native::ARRAY_RECURSIVE_KEY => true,
                ],
            ],
            null,
        ],
        // A forged recursion marker inside a nested use array short-circuits mapPointersValue().
        'marker_key_in_nested_use_array' => [
            [
                'function' => 'fn() => true',
                'this'     => null,
                'self'     => 'dummy_hash',
                'use'      => [
                    'nested' => [
                        Native::ARRAY_RECURSIVE_KEY => true,
                        'kept'                      => 1,
                    ],
                ],
            ],
            null,
        ],
    ];
});

test('unserialize handles combinatorial execution paths correctly', function (array $data, ?string $expectException) {
    $payload = [];

    foreach ($data as $key => $value) {
        $payload[(string) $key] = $value;
    }

    $serializable = new Native(fn () => true);

    if ($expectException !== null) {
        expect(fn () => $serializable->__unserialize($payload))->toThrow($expectException);

        return;
    }

    $serializable->__unserialize($payload);

    expect($serializable->getClosure())->toBeInstanceOf(Closure::class);
})->with('unserialize_matrix');

test('reconstructs a closure whose code is syntactically invalid as a statement', function () {
    $serializable = new Native(fn () => true);

    // "return return 42;" inside the stream wrapper raises a ParseError.
    expect(fn () => $serializable->__unserialize([
        'function' => 'return 42;',
        'this'     => null,
        'self'     => 'dummy_hash',
    ]))->toThrow(ParseError::class);
});

test('ignores a bound object identical to the instance being reconstructed', function () {
    $serializable = new Native(fn () => true);

    $serializable->__unserialize([
        'function' => 'fn() => true',
        'this'     => $serializable,
        'self'     => 'dummy_hash',
    ]);

    expect($serializable->getClosure())->toBeInstanceOf(Closure::class);
});

test('rebinds the reconstructed closure to a user-defined bound object', function () {
    $host = new class {
        public int $marker = 7;
    };

    $serializable = new Native(fn () => true);

    $serializable->__unserialize([
        'function' => 'fn() => $this->marker',
        'this'     => $host,
        'self'     => 'dummy_hash',
        'scope'    => get_class($host),
    ]);

    expect($serializable->getClosure()())->toBe(7);
});
