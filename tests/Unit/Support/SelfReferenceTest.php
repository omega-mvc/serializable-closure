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

use Omega\SerializableClosure\Support\SelfReference;

test('it exposes the given hash', function () {
    $reference = new SelfReference('abc123');

    expect($reference->hash)->toBe('abc123');
});

test('it round-trips through serialization', function () {
    $restored = unserialize(serialize(new SelfReference('deadbeef')));

    expect($restored)->toBeInstanceOf(SelfReference::class)
        ->and($restored->hash)->toBe('deadbeef');
});
