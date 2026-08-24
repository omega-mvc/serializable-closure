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

namespace Tests\Unit\Support;

use Closure;
use Omega\SerializableClosure\SerializableClosure;

test('the kitchen-sink closure keeps working after a round trip', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($host->sink())));

    $out = $restored();

    expect($out)->toContain('|N|')
        ->and($out)->toContain("'rich-constant'")
        ->and($out)->toContain('hi')
        ->and($out)->toContain('kind=')
        ->and($out)->not->toContain('__LINE__');
});

test('bound closures with complex host properties survive the round trip', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();

    $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($host->boundWithProps())));

    expect($restored())->toBe([
        'red',
        'Tests\Fixtures\Rich\RichHost',
        5,
        6,
        1,
    ]);
});
