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
use Omega\SerializableClosure\Support\ClosureScope;
use Omega\SerializableClosure\Support\ReflectionClosure;
use Tests\Fixtures\Suit;
use Tests\Fixtures\UserDefinedFixture;

function nativeOf(Closure $closure): Native
{
    return new Native($closure);
}

function emptyNative(): Native
{
    return (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();
}

function withScope(Native $native, ClosureScope $scope): Native
{
    (new ReflectionProperty(Native::class, 'scope'))->setValue($native, $scope);

    return $native;
}

test('getClosure throws when no closure has been constructed', function () {
    $native = (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();

    expect(fn () => $native->getClosure())->toThrow(ReflectionException::class);
});

test('getReflector throws when there is no closure to reflect', function () {
    $native = (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();

    expect(fn () => $native->getReflector())->toThrow(ReflectionException::class);
});

test('unserializable function code throws a reflection exception', function () {
    $payload = [
        'use' => [],
        'function' => '42;',
        'scope' => null,
        'this' => null,
        'self' => 'hash',
    ];

    $native = (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();

    expect(fn () => $native->__unserialize($payload))
        ->toThrow(ReflectionException::class, 'Failed to reconstruct');
});

test('an unknown scope class in the payload is ignored', function () {
    $closure = fn (): int => 1;
    $payload = nativeOf($closure)->__serialize();
    $payload['scope'] = 'App\\Ghosts\\NotReal';

    $restored = emptyNative();
    $restored->__unserialize($payload);

    expect($restored->getClosure()())->toBe(1);
});

test('a bound-this pointing at the wrapper itself is normalized away', function () {
    $closure = fn (): int => 1;
    $native = nativeOf($closure);

    $payload = $native->__serialize();
    $payload['this'] = $native;

    $target = (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();
    $target->__unserialize($payload);

    expect($target->getClosure()())->toBe(1);
});

test('captured variables survive the round trip', function () {
    $alpha = 10;
    $beta = 'b';

    $closure = fn (): array => [$alpha, $beta];

    $restored = emptyNative();
    $restored->__unserialize(nativeOf($closure)->__serialize());

    expect($restored->getClosure()())->toBe([10, 'b']);
});

test('nested closures inside captured arrays keep working after the round trip', function () {
    $inner = fn (): int => 2;
    $outer = fn (): int => ($inner)();

    $holder = ['list' => [$inner]];

    $closure = function () use ($outer, $holder): array {
        return [$outer(), ($holder['list'][0])()];
    };

    $restored = emptyNative();
    $restored->__unserialize(nativeOf($closure)->__serialize());

    expect($restored->getClosure()())->toBe([2, 2]);
});

test('user-defined objects are rebuilt property by property', function () {
    $fixture = new UserDefinedFixture();

    $closure = fn (): string => 'ok';
    $native = nativeOf($closure);
    $uses = ['fixture' => $fixture];

    $native->__serialize(); // warms reflector/scope state
    $scoped = withScope($native, new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);
    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['fixture'])->not->toBe($fixture)
        ->and($uses['fixture'])->toBeInstanceOf(UserDefinedFixture::class)
        ->and($uses['fixture']->label)->toBe('fixture')
        ->and($uses['fixture']->frozen)->toBe('immutable');
});

test('enums and datetimes pass through untouched', function () {
    $suit = Suit::Spades;
    $when = new DateTimeImmutable('2024-05-05T10:00:00Z');

    $closure = fn (): bool => true;
    $native = nativeOf($closure);
    $uses = ['suit' => $suit, 'when' => $when];

    $native->__serialize();
    $scoped = withScope($native, new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);
    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['suit'])->toBe($suit)
        ->and($uses['when'])->toBe($when);
});

test('transform hooks rewrite the use variables on serialization', function () {
    Native::$transformUseVariables = fn (array $vars): array => ['sealed' => count($vars)];

    $marker = 'value';
    $native = nativeOf(fn (): string => $marker);
    $data = $native->__serialize();

    Native::$transformUseVariables = null;

    expect($data['use'])->toBe(['sealed' => 1]);
});

test('resolution hooks restore transformed variables on deserialization', function () {
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

    $native = (new ReflectionClass(Native::class))->newInstanceWithoutConstructor();
    $native->__unserialize($payload);

    Native::$resolveUseVariables = null;

    expect($native->getClosure()())->toBe('ABC');
});

test('transform hooks returning non-iterables degrade to an empty use set', function () {
    Native::$transformUseVariables = fn (): string => 'not-an-array';

    $marker = 'kept-out';
    $native = nativeOf(fn (): string => $marker);
    $data = $native->__serialize();

    Native::$transformUseVariables = null;

    expect($data['use'])->toBe([]);
});

test('re-unserializing the same instance normalizes self-referencing this', function () {
    $payload = [
        'use' => [],
        'function' => 'fn (): int => 1;',
        'scope' => null,
        'this' => null,
        'self' => 'hash',
    ];

    $native = emptyNative();
    $native->__unserialize($payload);

    // Second pass: the payload now points back at the very wrapper being restored.
    $payload['this'] = $native;
    $native->__unserialize($payload);

    expect($native->getClosure()())->toBe(1);
});

test('the closure walker passes scalars through untouched', function () {
    $method = new ReflectionMethod(Native::class, 'wrapClosures');
    $method->setAccessible(true);

    $value = 'plain-string';

    expect($method->invoke(null, $value, new ClosureScope()))->toBe('plain-string');
});

test('self-referencing arrays hit the recursion sentinel once', function () {
    $loop = [];
    $loop['self'] = &$loop;
    $uses = ['loop' => &$loop];

    $scoped = withScope(nativeOf(fn (): int => 1), new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);
    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['loop'])->toBeArray();
});

test('aliased objects are serialized through the scope cache', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();
    $host->boundWithProps();

    $closure = fn (): int => 1;
    $scoped = withScope(nativeOf($closure), new ClosureScope());

    $method = new ReflectionMethod(Native::class, 'wrapClosures');
    $method->setAccessible(true);

    $storage = new ClosureScope();
    $first   = $method->invoke(null, $host->aliasOne, $storage);
    $second  = $method->invoke(null, $host->aliasTwo, $storage);
    $internal = $method->invoke(null, $host->internal, new ClosureScope());

    expect($first)->toBeInstanceOf(\Tests\Fixtures\UserDefinedFixture::class)
        ->and($second)->toBeInstanceOf(\Tests\Fixtures\UserDefinedFixture::class)
        ->and($internal)->toBeInstanceOf(ArrayObject::class);
});

test('the walker wraps closures inside plain arrays', function () {
    $method = new ReflectionMethod(Native::class, 'wrapClosures');
    $method->setAccessible(true);

    $payload = [fn (): int => 1, 'scalar', [fn (): int => 2]];

    $wrapped = $method->invoke(null, $payload, new ClosureScope());

    if (! is_array($wrapped) || ! isset($wrapped[2]) || ! is_array($wrapped[2])) {
        throw new Exception('Unexpected walker shape.');
    }

    expect($wrapped[0])->toBeInstanceOf(Native::class)
        ->and($wrapped[1])->toBe('scalar')
        ->and($wrapped[2][0])->toBeInstanceOf(Native::class);
});

test('stdClass payloads are cloned with their closures wrapped', function () {
    $method = new ReflectionMethod(Native::class, 'wrapClosures');
    $method->setAccessible(true);

    $box = new stdClass();
    $box->cb = fn (): int => 2;

    $wrapped = $method->invoke(null, $box, new ClosureScope());

    if (! $wrapped instanceof stdClass) {
        throw new Exception('Unexpected walker result.');
    }

    expect($wrapped->cb)->toBeInstanceOf(Native::class);
});

test('re-encountering the same object returns the cached instance', function () {
    $method = new ReflectionMethod(Native::class, 'wrapClosures');
    $method->setAccessible(true);

    $object = new Tests\Fixtures\UserDefinedFixture();
    $storage = new ClosureScope();

    $first = $method->invoke(null, $object, $storage);
    $second = $method->invoke(null, $object, $storage);

    expect($first)->toBeInstanceOf(Tests\Fixtures\UserDefinedFixture::class)
        ->and($first)->not->toBe($object)
        ->and($second)->toBe($first);
});

test('mapByReference mirrors the walker for arrays and stdclass', function () {
    $scoped = withScope(nativeOf(fn (): int => 1), new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);

    $uses = ['list' => [fn (): int => 3], 'box' => new stdClass(), 'when' => new DateTimeImmutable()];
    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['list'][0])->toBeInstanceOf(Native::class)
        ->and($uses['box'])->toBeInstanceOf(stdClass::class)
        ->and($uses['when'])->toBeInstanceOf(DateTimeImmutable::class);
});

test('resolve hooks without entries keep the payload untouched', function () {
    Native::$resolveUseVariables = null;

    $native = emptyNative();
    $native->__unserialize([
        'use' => [],
        'function' => 'fn (): int => 8;',
        'scope' => null,
        'this' => null,
        'self' => 'hash',
    ]);

    expect($native->getClosure()())->toBe(8);
});

test('the same captured closure maps to a single shared wrapper', function () {
    $scoped = withScope(nativeOf(fn (): int => 1), new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);

    $shared = fn (): int => 9;
    $uses = ['first' => $shared, 'second' => $shared];

    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['first'])->toBeInstanceOf(Native::class)
        ->and($uses['second'])->toBe($uses['first']);
});

test('the same captured object maps to a single rebuilt instance', function () {
    $scoped = withScope(nativeOf(fn (): int => 1), new ClosureScope());
    $method = new ReflectionMethod(Native::class, 'mapByReference');
    $method->setAccessible(true);

    $object = new \Tests\Fixtures\UserDefinedFixture();
    $uses = ['one' => $object, 'two' => $object];

    $args = [&$uses];
    $method->invokeArgs($scoped, $args);

    expect($uses['one'])->not->toBe($object)
        ->and($uses['two'])->toBe($uses['one']);
});
