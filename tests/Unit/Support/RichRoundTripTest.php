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

use Omega\SerializableClosure\SerializableClosure;
use Tests\TestCase;

final class RichRoundTripTest extends TestCase
{
    public function testTheKitchenSinkClosureKeepsWorkingAfterARoundTrip(): void
    {
        $host = new \Tests\Fixtures\Rich\RichHost();

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($host->sink())));

        $out = $restored();

        $this->assertIsString($out);

        $this->assertStringContainsString('|N|', $out);
        $this->assertStringContainsString("'rich-constant'", $out);
        $this->assertStringContainsString('hi', $out);
        $this->assertStringContainsString('kind=', $out);
        $this->assertStringNotContainsString('__LINE__', $out);
    }

    public function testBoundClosuresWithComplexHostPropertiesSurviveTheRoundTrip(): void
    {
        $host = new \Tests\Fixtures\Rich\RichHost();

        $restored = \Tests\Fixtures\RoundTrip::closure(serialize(new SerializableClosure($host->boundWithProps())));

        $this->assertSame([
            'red',
            'Tests\Fixtures\Rich\RichHost',
            5,
            6,
            1,
        ], $restored());
    }
}
