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

use Exception;
use Omega\SerializableClosure\Support\SelfReference;
use Tests\TestCase;

final class SelfReferenceTest extends TestCase
{
    public function testItExposesTheGivenHash(): void
    {
        $reference = new SelfReference('abc123');

        $this->assertSame('abc123', $reference->hash);
    }

    public function testItRoundTripsThroughSerialization(): void
    {
        $restored = unserialize(serialize(new SelfReference('deadbeef')));

        if (! $restored instanceof SelfReference) {
            throw new Exception('Unexpected restored type.');
        }

        $this->assertSame('deadbeef', $restored->hash);
    }
}
