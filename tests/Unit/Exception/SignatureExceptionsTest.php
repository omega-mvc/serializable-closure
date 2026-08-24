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

test('invalid signature exception carries a default message', function () {
    expect((new InvalidSignatureException())->getMessage())->not->toBe('');
});

test('missing secret key exception carries a default message', function () {
    expect((new MissingSecretKeyException())->getMessage())->toContain('secret key');
});

test('both exceptions accept a custom message', function () {
    expect((new InvalidSignatureException('custom'))->getMessage())->toBe('custom')
        ->and((new MissingSecretKeyException('custom'))->getMessage())->toBe('custom');
});
