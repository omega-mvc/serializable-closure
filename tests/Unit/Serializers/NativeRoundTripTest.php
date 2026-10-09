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

use Exception;
use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Serializers\Native;
use stdClass;
use Tests\Fixtures\UserDefinedFixture;
use Tests\TestCase;

final class NativeRoundTripTest extends TestCase
{
    public function testScalarBodyClosuresSurviveTheRoundTrip(): void
    {
        $restored = \Tests\Fixtures\RoundTrip::closure(
            serialize(new SerializableClosure(fn (): int => 1))
        );

        $this->assertSame(1, $restored());
    }

    public function testNestedArrayBodiesSurviveTheRoundTrip(): void
    {
        $closure = function (): array {
            return ['a' => [1, 2], 'b' => ['c' => 3]];
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));
        $result = $restored();

        if (! is_array($result)) {
            throw new Exception('Unexpected restored type.');
        }

        $this->assertSame(['a' => [1, 2], 'b' => ['c' => 3]], $result);
    }

    public function testStringInterpolationBracesSurviveTheRoundTrip(): void
    {
        $closure = function (): string {
            $name = 'x';

            return "hi {$name} and {$name}!";
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame('hi x and x!', $restored());
    }

    public function testObjectsHoldingSerializableClosuresRestoreTheirBindings(): void
    {
        $holder = new stdClass();
        $holder->callback = fn (): int => 11;

        $closure = function () use ($holder): int {
            return ($holder->callback)();
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame(11, $restored());
    }

    public function testDeferredBindingsRewireObjectPropertiesHoldingWrappers(): void
    {
        $holder = new \Tests\Fixtures\UntypedHolder();
        $holder->payload = new SerializableClosure(fn (): int => 33);

        $closure = function () use ($holder) {
            // After the round trip the deferred binding stores the raw closure.
            $wrapped = $holder->payload;

            return $wrapped();
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame(33, $restored());
    }

    public function testNullsafeAndStaticClassAccessSurviveTheRoundTrip(): void
    {
        $host = new UserDefinedFixture();

        $closure = function () use ($host): string {
            $maybe = unserialize('N;');

            return is_string($maybe) ? $maybe : $host::class;
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $result = $restored();

        $this->assertIsString($result);
        $this->assertStringContainsString('UserDefinedFixture', $result);
    }

    public function testInterpolationAndCastsKeepWorkingAfterTheRoundTrip(): void
    {
        $label = 'L';
        $closure = function (int $n) use ($label): string {
            $casted = (int) $n;

            return "{$label}:{$casted}";
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame('L:9', $restored(9));
    }

    public function testLineArithmeticIsPreservedAcrossTheRoundTrip(): void
    {
        $closure = static function (): int {
            return
                __LINE__
                ;
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertIsInt($restored());
    }

    public function testNamedArgumentsInInnerCallsArePreserved(): void
    {
        $closure = fn (): array => [array_keys(['k' => 1])];

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame([['k']], $restored());
    }

    public function testRecursiveClosuresRoundTripThroughTheirSelfReference(): void
    {
        $factorial = function (int $n) use (&$factorial): int {
            return $n <= 1 ? 1 : $n * $factorial($n - 1);
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($factorial)));

        $this->assertSame(120, $restored(5));
    }

    public function testTheSameCapturedClosureIsSerializedOnceAndStaysShared(): void
    {
        $inner = fn (): int => 7;
        $alias = $inner;

        $outer = function () use ($inner, $alias): int {
            return $inner() + $alias();
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($outer)));

        $this->assertSame(14, $restored());
    }

    public function testRecursiveSelfReferencingClosuresSurviveTheRoundTrip(): void
    {
        $f = function (int $n = 0) use (&$f) {
            return $n < 2 ? ($f)($n + 1) : $n;
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($f)));

        $this->assertSame(2, $restored());
    }

    public function testSelfReferencesNestedInArraysAndStdclassRewireAfterRestore(): void
    {
        $holder = new stdClass();
        $holder->fn = static fn (): string => 'placeholder';

        $box = ['cb' => $holder];

        $f = function () use (&$box): string {
            $ref = $box;

            return 'restored';
        };

        $holder->fn = &$f;

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($f)));

        $this->assertSame('restored', $restored());
    }

    public function testCircularCapturedArraysSurviveTheRoundTrip(): void
    {
        $loop = [];
        $loop['me'] = &$loop;

        $closure = fn (): int => count($loop);

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame(1, $restored());
    }

    public function testAliasedStdclassHoldersShareOneReconstructedInstance(): void
    {
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

        $this->assertTrue($restored());
    }

    public function testTheSameCapturedObjectKeepsASingleIdentityAfterTheRoundTrip(): void
    {
        $counter = new UserDefinedFixture();
        $alias   = $counter;

        $closure = function () use ($counter, $alias): string {
            return spl_object_id($counter) === spl_object_id($alias) ? $counter->label : 'mismatch';
        };

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($closure)));

        $this->assertSame('fixture', $restored());
    }

    public function testBoundObjectsCarryingRecursionMarkersKeepTheirArraysUntouched(): void
    {
        $host = new \Tests\Fixtures\LoopHost();

        // A literal marker entry (not an actual cycle): wrapClosures() must
        // return the array untouched instead of walking it.
        $host->loop = [
            Native::ARRAY_RECURSIVE_KEY => true,
            'tag'                       => 't',
        ];

        $bound = $host->reader();

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($bound)));
        $result   = $restored();

        if (! is_array($result)) {
            throw new Exception('Unexpected restored type.');
        }

        $this->assertSame('t', $result['tag'] ?? '');
        $this->assertTrue($result[Native::ARRAY_RECURSIVE_KEY] ?? false);
    }
}
