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
    $native = nativeOf(function () use ($marker) {});
    $data = $native->__serialize();

    Native::$transformUseVariables = null;

    expect($data['use'])->toBe(['sealed' => 1]);
});

test('resolution hooks restore transformed variables on deserialization', function () {
    Native::$resolveUseVariables = fn (array $vars): array => ['marker' => strtoupper((string) $vars['enveloped'])];

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
