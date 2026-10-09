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

namespace Tests\Unit;

use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\Signers\Hmac;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ParseError;
use ReflectionException;
use Tests\Fixtures\RoundTrip;
use Tests\TestCase;

/**
 * One combinatorial case: the raw payload handed to Native::__unserialize()
 * plus the exception the reconstruction must throw (null when it succeeds).
 *
 * @phpstan-type PathData array<string, mixed>
 * @phpstan-type PathCase array{0: PathData, 1: class-string<\Throwable>|null}
 */
final class SerializableClosurePathTest extends TestCase
{
    /**
     * Builds every (matrix × generated) combination, mirroring the original
     * Pest `->with('unserialize_matrix', 'generated_unserialize_matrix')`.
     *
     * Only the matrix values are handed to the test because the generated
     * dataset merely multiplies the execution count for path coverage — the
     * Pest data provider fed both rows to a two-parameter closure, so the
     * generated values were appended after the ones actually consumed.
     *
     * @return array<string, PathCase>
     */
    public static function unserializeMatrix(): array
    {
        $matrix = [
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

        $generated = self::generatedUnserializeMatrix();

        $rows = [];

        foreach ($matrix as $matrixName => $case) {
            foreach (array_keys($generated) as $generatedName) {
                $rows[sprintf('dataset "%s" / dataset "%s"', $matrixName, $generatedName)] = $case;
            }
        }

        return $rows;
    }

    /**
     * Programmatic sweep of the __unserialize() decision space, keyed by a
     * human-readable description so duplicate descriptions collapse exactly as
     * they did under Pest.
     *
     * @return array<string, PathCase>
     */
    private static function generatedUnserializeMatrix(): array
    {
        $functions = [
            'fn() => true'                 => null,
            'static fn () => true'         => null,
            'function () { return true; }' => null,
            '42'                           => ReflectionException::class,
            12345                          => ReflectionException::class,
        ];
        $scopes     = [null, RoundTrip::class, 'NoSuchClassAnywhere', ['bad_scope']];
        $thisValues = [null, ['not_an_object']];
        $uses       = [
            [],
            ['v' => 'x'],
            ['v' => 'x', 7 => 'integer key dropped'],
            [Native::ARRAY_RECURSIVE_KEY => true],
        ];

        $rows = [];

        foreach ($functions as $function => $throws) {
            foreach ($scopes as $scope) {
                foreach ($thisValues as $bound) {
                    foreach ($uses as $use) {
                        // Static closures cannot rebind: keep such rows coherent.
                        if ($bound !== null && str_starts_with((string) $function, 'static')) {
                            continue;
                        }

                        $payload = array_filter([
                            'function' => $function,
                            'this'     => $bound,
                            'self'     => 'dummy_hash',
                            'scope'    => $scope,
                            'use'      => $use === [] ? null : $use,
                        ], static fn (mixed $value): bool => $value !== null);

                        $name = sprintf(
                            '%s | scope=%s | this=%s | use=%d',
                            substr(var_export($function, true), 0, 24),
                            var_export($scope, true),
                            var_export($bound, true),
                            count($use)
                        );

                        $rows[$name] = [$payload, $throws];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>     $data
     * @param class-string<\Throwable>|null $expectException
     */
    #[DataProvider('unserializeMatrix')]
    public function testUnserializeHandlesCombinatorialExecutionPathsCorrectly(
        array $data,
        ?string $expectException
    ): void {
        $payload = [];

        foreach ($data as $key => $value) {
            $payload[(string) $key] = $value;
        }

        $serializable = new Native(fn () => true);

        if ($expectException !== null) {
            $this->expectException($expectException);
            $serializable->__unserialize($payload);

            return;
        }

        $serializable->__unserialize($payload);

        // Every successful execution path reconstructs a callable closure; all
        // success payloads build a body that returns true.
        $this->assertTrue($serializable->getClosure()());
    }

    public function testReconstructsAClosureWhoseCodeIsSyntacticallyInvalidAsAStatement(): void
    {
        $serializable = new Native(fn () => true);

        // "return return 42;" inside the stream wrapper raises a ParseError.
        $this->expectException(ParseError::class);
        $serializable->__unserialize([
            'function' => 'return 42;',
            'this'     => null,
            'self'     => 'dummy_hash',
        ]);
    }

    public function testIgnoresABoundObjectIdenticalToTheInstanceBeingReconstructed(): void
    {
        $serializable = new Native(fn () => true);

        $serializable->__unserialize([
            'function' => 'fn() => true',
            'this'     => $serializable,
            'self'     => 'dummy_hash',
        ]);

        $this->assertTrue($serializable->getClosure()());
    }

    public function testRebindsTheReconstructedClosureToAUserDefinedBoundObject(): void
    {
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

        $this->assertSame(7, $serializable->getClosure()());
    }

    /*
    |--------------------------------------------------------------------------
    | Stress tests
    |--------------------------------------------------------------------------
    |
    | The iteration budget is provided by Tests\TestCase::stressIterations().
    | Stress tests are skipped automatically when the budget resolves to ≤ 1
    | (i.e. no stress budget configured).
    |
    */

    #[Group('stress')]
    public function testStressRepeatedNativeSerializeUnserializeRoundTrips(): void
    {
        if (TestCase::stressIterations() <= 1) {
            $this->markTestSkipped('Stress tests disabled (stressIterations() ≤ 1).');
        }

        $closure = fn (int $n): int => $n * 2;

        for ($i = 0; $i < TestCase::stressIterations(); $i++) {
            $serializable = new Native($closure);
            $payload      = $serializable->__serialize();

            $restored = new Native(fn () => true);
            $restored->__unserialize($payload);

            $fn = $restored->getClosure();
            $this->assertSame(42, $fn(21));
        }
    }

    #[Group('stress')]
    public function testStressRepeatedSignedSerializeUnserializeRoundTrips(): void
    {
        if (TestCase::stressIterations() <= 1) {
            $this->markTestSkipped('Stress tests disabled (stressIterations() ≤ 1).');
        }

        $secretKey = 'stress-test-secret-key';
        Signed::$signer = new Hmac($secretKey);
        $closure   = fn (string $label): string => "stress: {$label}";

        for ($i = 0; $i < TestCase::stressIterations(); $i++) {
            $signed     = new Signed($closure);
            $payload    = $signed->__serialize();

            $restored   = new Signed(fn () => true);
            $restored->__unserialize($payload);

            $fn = $restored->getClosure();
            $this->assertSame('stress: ok', $fn('ok'));
        }
    }

    #[Group('stress')]
    public function testStressRepeatedComplexClosuresWithBoundObjectAndUseVariables(): void
    {
        if (TestCase::stressIterations() <= 1) {
            $this->markTestSkipped('Stress tests disabled (stressIterations() ≤ 1).');
        }

        $host   = new class {
            public int $value = 10;
        };
        $multiplier = 3;

        for ($i = 0; $i < TestCase::stressIterations(); $i++) {
            $serializable = new Native(fn () => true);

            $serializable->__unserialize([
                'function' => 'fn (int $n): int => $this->value + $n * $multiplier',
                'this'     => $host,
                'self'     => 'dummy_hash',
                'scope'    => get_class($host),
                'use'      => ['multiplier' => $multiplier],
            ]);

            $fn = $serializable->getClosure();
            $this->assertSame(25, $fn(5));
        }
    }
}
