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

namespace Tests\Unit;

use Exception;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\UnsignedSerializableClosure;
use Tests\TestCase;

final class UnsignedSerializableClosureTest extends TestCase
{
    public function testItInvokesTheWrappedClosureWithForwardedArguments(): void
    {
        $unsigned = new UnsignedSerializableClosure(fn (int $a, int $b): int => $a * $b);

        $this->assertSame(42, $unsigned(6, 7));
    }

    public function testItsPayloadCarriesOnlyTheNativeSerializable(): void
    {
        $unsigned = new UnsignedSerializableClosure(fn (): string => 'x');

        $data = $unsigned->__serialize();

        $this->assertSame(['serializable'], array_keys($data));
        $this->assertInstanceOf(Native::class, $data['serializable']);
    }

    public function testItSurvivesAFullSerializationCycle(): void
    {
        $factor = 3;
        $payload = serialize(new UnsignedSerializableClosure(fn (int $x): int => $x * $factor));

        $restored = unserialize($payload);

        if (! $restored instanceof UnsignedSerializableClosure) {
            throw new Exception('Unexpected restored type.');
        }

        $this->assertSame(15, $restored(5));
    }
}
