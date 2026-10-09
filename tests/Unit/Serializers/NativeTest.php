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

use ArrayObject;
use Closure;
use DateTimeImmutable;
use Exception;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Support\ClosureScope;
use Omega\SerializableClosure\Support\SelfReference;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Tests\Fixtures\Suit;
use Tests\Fixtures\UserDefinedFixture;
use Tests\TestCase;

final class NativeTest extends TestCase
{
    private static function nativeOf(Closure $closure): Native
    {
        return new Native($closure);
    }

    private static function emptyNative(): Native
    {
        return (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();
    }

    private static function withScope(Native $native, ClosureScope $scope): Native
    {
        (new ReflectionProperty(Native::class, 'scope'))->setValue($native, $scope);

        return $native;
    }

    public function testGetClosureThrowsWhenNoClosureHasBeenConstructed(): void
    {
        $native = self::emptyNative();

        $this->expectException(ReflectionException::class);
        $native->getClosure();
    }

    public function testGetReflectorThrowsWhenThereIsNoClosureToReflect(): void
    {
        $native = self::emptyNative();

        $this->expectException(ReflectionException::class);
        $native->getReflector();
    }

    public function testUnserializableFunctionCodeThrowsAReflectionException(): void
    {
        $payload = [
            'use' => [],
            'function' => '42;',
            'scope' => null,
            'this' => null,
            'self' => 'hash',
        ];

        $native = self::emptyNative();

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Failed to reconstruct');
        $native->__unserialize($payload);
    }

    public function testAnUnknownScopeClassInThePayloadIsIgnored(): void
    {
        $closure = fn (): int => 1;
        $payload = self::nativeOf($closure)->__serialize();
        $payload['scope'] = 'App\\Ghosts\\NotReal';

        $restored = self::emptyNative();
        $restored->__unserialize($payload);

        $this->assertSame(1, $restored->getClosure()());
    }

    public function testABoundThisPointingAtTheWrapperItselfIsNormalizedAway(): void
    {
        $closure = fn (): int => 1;
        $native = self::nativeOf($closure);

        $payload = $native->__serialize();
        $payload['this'] = $native;

        $target = self::emptyNative();
        $target->__unserialize($payload);

        $this->assertSame(1, $target->getClosure()());
    }

    public function testCapturedVariablesSurviveTheRoundTrip(): void
    {
        $alpha = 10;
        $beta = 'b';

        $closure = fn (): array => [$alpha, $beta];

        $restored = self::emptyNative();
        $restored->__unserialize(self::nativeOf($closure)->__serialize());

        $this->assertSame([10, 'b'], $restored->getClosure()());
    }

    public function testNestedClosuresInsideCapturedArraysKeepWorkingAfterTheRoundTrip(): void
    {
        $inner = fn (): int => 2;
        $outer = fn (): int => ($inner)();

        $holder = ['list' => [$inner]];

        $closure = function () use ($outer, $holder): array {
            return [$outer(), ($holder['list'][0])()];
        };

        $restored = self::emptyNative();
        $restored->__unserialize(self::nativeOf($closure)->__serialize());

        $this->assertSame([2, 2], $restored->getClosure()());
    }

    public function testUserDefinedObjectsAreRebuiltPropertyByProperty(): void
    {
        $fixture = new UserDefinedFixture();

        $closure = fn (): string => 'ok';
        $native = self::nativeOf($closure);
        $uses = ['fixture' => $fixture];

        $native->__serialize(); // warms reflector/scope state
        $scoped = self::withScope($native, new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');
        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertNotSame($fixture, $uses['fixture']);
        $this->assertInstanceOf(UserDefinedFixture::class, $uses['fixture']);
        $this->assertSame('fixture', $uses['fixture']->label);
        $this->assertSame('immutable', $uses['fixture']->frozen);
    }

    public function testEnumsAndDatetimesPassThroughUntouched(): void
    {
        $suit = Suit::Spades;
        $when = new DateTimeImmutable('2024-05-05T10:00:00Z');

        $closure = fn (): bool => true;
        $native = self::nativeOf($closure);
        $uses = ['suit' => $suit, 'when' => $when];

        $native->__serialize();
        $scoped = self::withScope($native, new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');
        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertSame($suit, $uses['suit']);
        $this->assertSame($when, $uses['when']);
    }

    public function testTransformHooksRewriteTheUseVariablesOnSerialization(): void
    {
        Native::$transformUseVariables = fn (array $vars): array => ['sealed' => count($vars)];

        $marker = 'value';
        $native = self::nativeOf(fn (): string => $marker);
        $data = $native->__serialize();

        Native::$transformUseVariables = null;

        $this->assertSame(['sealed' => 1], $data['use']);
    }

    public function testResolutionHooksRestoreTransformedVariablesOnDeserialization(): void
    {
        Native::$resolveUseVariables = function (array $vars): array {
            return ['marker' => strtoupper(is_string($raw = $vars['enveloped'] ?? null) ? $raw : '')];
        };

        $payload = [
            'use' => ['enveloped' => 'abc'],
            'function' => 'fn (): string => $marker;',
            'scope' => null,
            'this' => null,
            'self' => 'hash',
        ];

        $native = self::emptyNative();
        $native->__unserialize($payload);

        Native::$resolveUseVariables = null;

        $this->assertSame('ABC', $native->getClosure()());
    }

    public function testTransformHooksReturningNonIterablesDegradeToAnEmptyUseSet(): void
    {
        Native::$transformUseVariables = fn (): string => 'not-an-array';

        $marker = 'kept-out';
        $native = self::nativeOf(fn (): string => $marker);
        $data = $native->__serialize();

        Native::$transformUseVariables = null;

        $this->assertSame([], $data['use']);
    }

    public function testReUnserializingTheSameInstanceNormalizesSelfReferencingThis(): void
    {
        $payload = [
            'use' => [],
            'function' => 'fn (): int => 1;',
            'scope' => null,
            'this' => null,
            'self' => 'hash',
        ];

        $native = self::emptyNative();
        $native->__unserialize($payload);

        // Second pass: the payload now points back at the very wrapper being restored.
        $payload['this'] = $native;
        $native->__unserialize($payload);

        $this->assertSame(1, $native->getClosure()());
    }

    public function testTheClosureWalkerPassesScalarsThroughUntouched(): void
    {
        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $value = 'plain-string';

        $this->assertSame('plain-string', $method->invoke(null, $value, new ClosureScope()));
    }

    public function testSelfReferencingArraysHitTheRecursionSentinelOnce(): void
    {
        $loop = [];
        $loop['self'] = &$loop;
        $uses = ['loop' => &$loop];

        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');
        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertArrayHasKey('self', $uses['loop']);
        $this->assertArrayNotHasKey(Native::ARRAY_RECURSIVE_KEY, $uses['loop']);
    }

    public function testAliasedObjectsAreSerializedThroughTheScopeCache(): void
    {
        $host = new \Tests\Fixtures\Rich\RichHost();
        $host->boundWithProps();

        $closure = fn (): int => 1;
        $scoped = self::withScope(self::nativeOf($closure), new ClosureScope());

        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $storage = new ClosureScope();
        $first   = $method->invoke(null, $host->aliasOne, $storage);
        $second  = $method->invoke(null, $host->aliasTwo, $storage);
        $internal = $method->invoke(null, $host->internal, new ClosureScope());

        $this->assertInstanceOf(UserDefinedFixture::class, $first);
        $this->assertInstanceOf(UserDefinedFixture::class, $second);
        $this->assertInstanceOf(ArrayObject::class, $internal);
    }

    public function testTheWalkerWrapsClosuresInsidePlainArrays(): void
    {
        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $payload = [fn (): int => 1, 'scalar', [fn (): int => 2]];

        $wrapped = $method->invoke(null, $payload, new ClosureScope());

        if (! is_array($wrapped) || ! isset($wrapped[2]) || ! is_array($wrapped[2])) {
            throw new Exception('Unexpected walker shape.');
        }

        $this->assertInstanceOf(Native::class, $wrapped[0]);
        $this->assertSame('scalar', $wrapped[1]);
        $this->assertInstanceOf(Native::class, $wrapped[2][0]);
    }

    public function testStdClassPayloadsAreClonedWithTheirClosuresWrapped(): void
    {
        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $box = new stdClass();
        $box->cb = fn (): int => 2;

        $wrapped = $method->invoke(null, $box, new ClosureScope());

        if (! $wrapped instanceof stdClass) {
            throw new Exception('Unexpected walker result.');
        }

        $this->assertInstanceOf(Native::class, $wrapped->cb);
    }

    public function testReEncounteringTheSameObjectReturnsTheCachedInstance(): void
    {
        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $object  = new UserDefinedFixture();
        $storage = new ClosureScope();

        $first  = $method->invoke(null, $object, $storage);
        $second = $method->invoke(null, $object, $storage);

        $this->assertInstanceOf(UserDefinedFixture::class, $first);
        $this->assertNotSame($object, $first);
        $this->assertSame($first, $second);
    }

    public function testMapByReferenceMirrorsTheWalkerForArraysAndStdclass(): void
    {
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');

        $uses = ['list' => [fn (): int => 3], 'box' => new stdClass(), 'when' => new DateTimeImmutable()];
        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertInstanceOf(Native::class, $uses['list'][0]);
        $this->assertInstanceOf(stdClass::class, $uses['box']);
        $this->assertInstanceOf(DateTimeImmutable::class, $uses['when']);
    }

    public function testResolveHooksWithoutEntriesKeepThePayloadUntouched(): void
    {
        Native::$resolveUseVariables = null;

        $native = self::emptyNative();
        $native->__unserialize([
            'use' => [],
            'function' => 'fn (): int => 8;',
            'scope' => null,
            'this' => null,
            'self' => 'hash',
        ]);

        $this->assertSame(8, $native->getClosure()());
    }

    public function testTheSameCapturedClosureMapsToASingleSharedWrapper(): void
    {
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');

        $shared = fn (): int => 9;
        $uses = ['first' => $shared, 'second' => $shared];

        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertSame($uses['first'], $uses['second']);
        $this->assertInstanceOf(Native::class, $uses['first']);
    }

    public function testTheSameCapturedObjectMapsToASingleRebuiltInstance(): void
    {
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');

        $object = new UserDefinedFixture();
        $uses = ['one' => $object, 'two' => $object];

        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertNotSame($object, $uses['one']);
        $this->assertSame($uses['one'], $uses['two']);
    }

    public function testSelfReferencingClosureInUseVariablesMapsViaSelfReference(): void
    {
        $outer = fn (): int => 1;
        $native = self::nativeOf($outer);
        $payload = $native->__serialize();

        $selfRef = new SelfReference($payload['self']);

        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapPointers');

        $data = ['ref' => $selfRef];
        $deferred = [];
        $args = [&$data, $payload['self'], &$deferred];
        $method->invokeArgs($scoped, $args);

        $this->assertInstanceOf(Closure::class, $data['ref']);
    }

    public function testRecursiveArrayInUseVariablesIsHandledByMapByReference(): void
    {
        $loop = [];
        $loop['self'] = &$loop;
        $loop['value'] = 42;

        $uses = ['data' => &$loop];

        $scoped = self::withScope(self::nativeOf(fn (): int => 1), new ClosureScope());
        $method = new ReflectionMethod(Native::class, 'mapByReference');
        $args = [&$uses];
        $method->invokeArgs($scoped, $args);

        $this->assertArrayHasKey('self', $uses['data']);
        $this->assertArrayHasKey('value', $uses['data']);
        $this->assertArrayNotHasKey(Native::ARRAY_RECURSIVE_KEY, $uses['data']);
        $this->assertSame(42, $uses['data']['value']);
    }

    public function testWrapClosuresReturnsCachedStdClassOnSecondVisit(): void
    {
        $method = new ReflectionMethod(Native::class, 'wrapClosures');

        $box = new stdClass();
        $box->name = 'test';
        $storage = new ClosureScope();

        $first  = $method->invoke(null, $box, $storage);
        $second = $method->invoke(null, $box, $storage);

        $this->assertInstanceOf(stdClass::class, $first);
        $this->assertSame($first, $second);
    }

    public function testMapPointersValueHandlesSelfReferenceInsideNestedArray(): void
    {
        $closure = fn (): int => 1;
        $native = self::nativeOf($closure);
        $payload = $native->__serialize();

        $selfRef = new SelfReference($payload['self']);

        $scope = new ClosureScope();
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), $scope);
        $method = new ReflectionMethod(Native::class, 'mapPointersValue');

        $value = ['nested' => ['ref' => $selfRef]];
        $deferred = [];
        $method->invokeArgs($scoped, [&$value, $payload['self'], &$deferred, $scope]);

        $this->assertInstanceOf(Closure::class, $value['nested']['ref']);
    }

    public function testMapPointersValueHandlesSelfReferenceInsideStdClass(): void
    {
        $closure = fn (): int => 1;
        $native = self::nativeOf($closure);
        $payload = $native->__serialize();

        $selfRef = new SelfReference($payload['self']);

        $scope = new ClosureScope();
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), $scope);
        $method = new ReflectionMethod(Native::class, 'mapPointersValue');

        $box = new stdClass();
        $box->ref = $selfRef;
        $deferred = [];
        $method->invokeArgs($scoped, [&$box, $payload['self'], &$deferred, $scope]);

        $this->assertInstanceOf(Closure::class, $box->ref);
    }

    public function testMapPointersValueReturnsEarlyForAlreadySeenStdClassScope(): void
    {
        $scope = new ClosureScope();
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), $scope);
        $method = new ReflectionMethod(Native::class, 'mapPointersValue');

        $box = new stdClass();
        $box->name = 'original';

        $storage = new ClosureScope();
        $storage[$box] = true;

        $deferred = [];
        $method->invokeArgs($scoped, [&$box, 'hash', &$deferred, $storage]);

        $this->assertSame('original', $box->name);
    }

    public function testMapPointersValueReturnsEarlyForAlreadySeenObjectScope(): void
    {
        $scope = new ClosureScope();
        $scoped = self::withScope(self::nativeOf(fn (): int => 1), $scope);
        $method = new ReflectionMethod(Native::class, 'mapPointersValue');

        $obj = new UserDefinedFixture();

        $storage = new ClosureScope();
        $storage[$obj] = true;

        $deferred = [];
        $method->invokeArgs($scoped, [&$obj, 'hash', &$deferred, $storage]);

        $this->assertSame('fixture', $obj->label);
    }
}
