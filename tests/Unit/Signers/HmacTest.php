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

use Omega\SerializableClosure\Signers\Hmac;

test('sign returns the payload with a lowercase hex hash', function () {
    $signature = (new Hmac('secret'))->sign('DATA');

    expect($signature)->toHaveKeys(['serializable', 'hash'])
        ->and($signature['serializable'])->toBe('DATA')
        ->and($signature['hash'])->toMatch('/^[0-9a-f]{64}$/');
});

test('verify accepts a signature produced with the same secret', function () {
    $signer = new Hmac('secret');

    expect($signer->verify($signer->sign('payload')))->toBeTrue();
});

test('verify rejects a signature produced with another secret', function () {
    $signed = (new Hmac('secret'))->sign('payload');

    expect((new Hmac('intruder'))->verify($signed))->toBeFalse();
});

test('verify rejects tampered payloads', function () {
    $signer = new Hmac('secret');
    $signature = $signer->sign('payload');
    $signature['serializable'] = 'PAYLOAD';

    expect($signer->verify($signature))->toBeFalse();
});
