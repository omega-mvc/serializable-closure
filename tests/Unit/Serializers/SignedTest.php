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

use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\Signers\Hmac;

test('serializing without a signer throws', function () {
    Signed::$signer = null;

    $signed = new Signed(fn (): int => 1);

    expect(fn () => $signed->__serialize())->toThrow(MissingSecretKeyException::class);
});

test('serializing with a signer returns the signed envelope', function () {
    Signed::$signer = new Hmac('secret');

    $data = (new Signed(fn (): int => 1))->__serialize();

    expect($data)->toHaveKeys(['serializable', 'hash']);

    Signed::$signer = null;
});

test('unserializing without a signer is rejected fail-closed', function () {
    Signed::$signer = new Hmac('secret');
    $signature = (new Signed(fn (): int => 1))->__serialize();
    Signed::$signer = null;

    expect(fn () => (new Signed(fn (): int => 0))->__unserialize($signature))
        ->toThrow(MissingSecretKeyException::class);
});

test('unserializing with a wrong signer is rejected', function () {
    Signed::$signer = new Hmac('right');
    $signature = (new Signed(fn (): int => 1))->__serialize();
    Signed::$signer = new Hmac('wrong');

    expect(fn () => (new Signed(fn (): int => 0))->__unserialize($signature))
        ->toThrow(InvalidSignatureException::class);

    Signed::$signer = null;
});

test('unserializing an envelope whose payload is not serializable is rejected', function () {
    // A validly-signed envelope whose inner payload unserializes to something
    // that is not a SerializableInterface implementation.
    $hmac = new Hmac('secret');
    $forged = $hmac->sign(serialize(new stdClass()));

    Signed::$signer = $hmac;

    expect(fn () => (new Signed(fn (): int => 0))->__unserialize($forged))
        ->toThrow(InvalidSignatureException::class);

    Signed::$signer = null;
});

test('unserializing a valid envelope restores the closure', function () {
    Signed::$signer = new Hmac('secret');

    $envelope = (new Signed(fn (): int => 3))->__serialize();
    $restored = new Signed(fn (): int => 0);
    $restored->__unserialize($envelope);

    Signed::$signer = null;

    expect($restored->getClosure()())->toBe(3)
        ->and($restored())->toBe(3);
});
