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

use Omega\SerializableClosure\Exception\MissingSecretKeyException;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Signers\Hmac;
use Omega\SerializableClosure\UnsignedSerializableClosure;

/*
|--------------------------------------------------------------------------
| Serializable Closure Tests
|--------------------------------------------------------------------------
|
| NOTE: unserialization round-trips are currently broken by the stream
| wrapper method naming issue documented in AGENTS.md; these tests cover
| the serialize side and the signing behaviour until that lands.
|
*/

test('it serializes an unsigned serializable closure', function () {
    $serialized = serialize(new UnsignedSerializableClosure(fn (int $a): int => $a + 1));

    expect($serialized)->not->toBeEmpty();
});

test('it uses native serialization without a secret key', function () {
    SerializableClosure::setSecretKey(null);
    expect(Signed::$signer)->toBeNull();

    $serialized = serialize(new SerializableClosure(fn (): int => 42));

    expect($serialized)->not->toBeEmpty();
});

test('it signs serialization when a secret key is set', function () {
    SerializableClosure::setSecretKey('secret-key');
    expect(Signed::$signer)->toBeInstanceOf(Hmac::class);

    $serialized = serialize(new SerializableClosure(fn (): int => 7));

    expect($serialized)->toContain('hash');
});

test('it clears the signer when the secret key is removed', function () {
    SerializableClosure::setSecretKey('secret-key');
    expect(Signed::$signer)->not()->toBeNull();

    SerializableClosure::setSecretKey(null);
    expect(Signed::$signer)->toBeNull();
});

test('it throws missing secret key exception when key is unset before signed serialize', function () {
    SerializableClosure::setSecretKey('secret-key');

    $serializable = new SerializableClosure(fn (): int => 1);

    SerializableClosure::setSecretKey(null);

    expect(fn () => serialize($serializable))->toThrow(MissingSecretKeyException::class);
});

test('it round-trips an unsigned closure', function () {
    $y = 10;

    $closure = fn (int $a): int => $a + $y + 1;

    $restored = unserialize(serialize(new SerializableClosure($closure)));

    if (! $restored instanceof SerializableClosure) {
        throw new Exception('Restored value is not a SerializableClosure.');
    }

    expect($restored(41))->toBe(52);
});

test('it round-trips a signed closure and verifies the signature', function () {
    SerializableClosure::setSecretKey('secret-key');

    $payload = serialize(new SerializableClosure(fn (int $x): int => $x * 3));

    $restored = unserialize($payload);

    SerializableClosure::setSecretKey(null);

    if (! $restored instanceof SerializableClosure) {
        throw new Exception('Restored value is not a SerializableClosure.');
    }

    expect($restored(4))->toBe(12);
});

test('loading a signed payload without a key is rejected fail-closed', function () {
    SerializableClosure::setSecretKey('k');
    $payload = serialize(new SerializableClosure(fn (): int => 5));
    SerializableClosure::setSecretKey(null);

    expect(fn () => unserialize($payload))->toThrow(MissingSecretKeyException::class);
});

test('its payload carries only the serializable component', function () {
    $data = (new SerializableClosure(fn (): int => 1))->__serialize();

    expect(array_keys($data))->toBe(['serializable']);
});

test('the unsigned factory builds an unsigned wrapper around the closure', function () {
    $closure = fn (): int => 7;

    $unsigned = SerializableClosure::unsigned($closure);

    expect($unsigned->getClosure())->toBe($closure)
        ->and($unsigned())->toBe(7);
});

test('it forwards arguments to the underlying serializer when invoked', function () {
    $serializable = new SerializableClosure(fn (int $a, int $b): int => $a - $b);

    expect($serializable(10, 4))->toBe(6);
});

test('extension hooks can be set and cleared', function () {
    SerializableClosure::transformUseVariablesUsing(fn (array $vars): array => $vars);
    SerializableClosure::resolveUseVariablesUsing(fn (array $vars): array => $vars);

    expect(Native::$transformUseVariables)->toBeInstanceOf(Closure::class)
        ->and(Native::$resolveUseVariables)->toBeInstanceOf(Closure::class);

    SerializableClosure::transformUseVariablesUsing(null);
    SerializableClosure::resolveUseVariablesUsing(null);

    expect(Native::$transformUseVariables)->toBeNull()
        ->and(Native::$resolveUseVariables)->toBeNull();
});

test('transform hook results are filtered down to their string-keyed entries', function () {
    SerializableClosure::transformUseVariablesUsing(
        fn (array $vars): \ArrayIterator => new \ArrayIterator([
            'kept'   => $vars['in'],
            3        => 'integer key is dropped',
        ])
    );

    expect(Native::applyTransformHook(['in' => 'v']))->toBe(['kept' => 'v']);

    SerializableClosure::transformUseVariablesUsing(null);

    expect(Native::$transformUseVariables)->toBeNull();
});
