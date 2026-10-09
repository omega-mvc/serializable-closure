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

use Closure;
use Exception;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Signers\Hmac;
use Omega\SerializableClosure\UnsignedSerializableClosure;
use Tests\TestCase;

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

final class SerializableClosureTest extends TestCase
{
    public function testItSerializesAnUnsignedSerializableClosure(): void
    {
        $serialized = serialize(new UnsignedSerializableClosure(fn (int $a): int => $a + 1));

        $this->assertNotEmpty($serialized);
    }

    public function testItUsesNativeSerializationWithoutASecretKey(): void
    {
        SerializableClosure::setSecretKey(null);
        $this->assertNull(Signed::$signer);

        $serialized = serialize(new SerializableClosure(fn (): int => 42));

        $this->assertNotEmpty($serialized);
    }

    public function testItSignsSerializationWhenASecretKeyIsSet(): void
    {
        SerializableClosure::setSecretKey('secret-key');
        $this->assertInstanceOf(Hmac::class, Signed::$signer);

        $serialized = serialize(new SerializableClosure(fn (): int => 7));

        $this->assertStringContainsString('hash', $serialized);
    }

    public function testItClearsTheSignerWhenTheSecretKeyIsRemoved(): void
    {
        SerializableClosure::setSecretKey('secret-key');
        $this->assertNotNull(Signed::$signer);

        SerializableClosure::setSecretKey(null);
        $this->assertNull(Signed::$signer);
    }

    public function testItThrowsMissingSecretKeyExceptionWhenKeyIsUnsetBeforeSignedSerialize(): void
    {
        SerializableClosure::setSecretKey('secret-key');

        $serializable = new SerializableClosure(fn (): int => 1);

        SerializableClosure::setSecretKey(null);

        $this->expectException(MissingSecretKeyException::class);
        serialize($serializable);
    }

    public function testItRoundTripsAnUnsignedClosure(): void
    {
        $y = 10;

        $closure = fn (int $a): int => $a + $y + 1;

        $restored = unserialize(serialize(new SerializableClosure($closure)));

        if (! $restored instanceof SerializableClosure) {
            throw new Exception('Restored value is not a SerializableClosure.');
        }

        $this->assertSame(52, $restored(41));
    }

    public function testItRoundTripsASignedClosureAndVerifiesTheSignature(): void
    {
        SerializableClosure::setSecretKey('secret-key');

        $payload = serialize(new SerializableClosure(fn (int $x): int => $x * 3));

        $restored = unserialize($payload);

        SerializableClosure::setSecretKey(null);

        if (! $restored instanceof SerializableClosure) {
            throw new Exception('Restored value is not a SerializableClosure.');
        }

        $this->assertSame(12, $restored(4));
    }

    public function testLoadingASignedPayloadWithoutAKeyIsRejectedFailClosed(): void
    {
        SerializableClosure::setSecretKey('k');
        $payload = serialize(new SerializableClosure(fn (): int => 5));
        SerializableClosure::setSecretKey(null);

        $this->expectException(MissingSecretKeyException::class);
        unserialize($payload);
    }

    public function testItsPayloadCarriesOnlyTheSerializableComponent(): void
    {
        $data = (new SerializableClosure(fn (): int => 1))->__serialize();

        $this->assertSame(['serializable'], array_keys($data));
    }

    public function testTheUnsignedFactoryBuildsAnUnsignedWrapperAroundTheClosure(): void
    {
        $closure = fn (): int => 7;

        $unsigned = SerializableClosure::unsigned($closure);

        $this->assertSame($closure, $unsigned->getClosure());
        $this->assertSame(7, $unsigned());
    }

    public function testItForwardsArgumentsToTheUnderlyingSerializerWhenInvoked(): void
    {
        $serializable = new SerializableClosure(fn (int $a, int $b): int => $a - $b);

        $this->assertSame(6, $serializable(10, 4));
    }

    public function testExtensionHooksCanBeSetAndCleared(): void
    {
        SerializableClosure::transformUseVariablesUsing(fn (array $vars): array => $vars);
        SerializableClosure::resolveUseVariablesUsing(fn (array $vars): array => $vars);

        $this->assertInstanceOf(Closure::class, Native::$transformUseVariables);
        $this->assertInstanceOf(Closure::class, Native::$resolveUseVariables);

        SerializableClosure::transformUseVariablesUsing(null);
        SerializableClosure::resolveUseVariablesUsing(null);

        $this->assertNull(Native::$transformUseVariables);
        $this->assertNull(Native::$resolveUseVariables);
    }

    public function testTransformHookResultsAreFilteredDownToTheirStringKeyedEntries(): void
    {
        SerializableClosure::transformUseVariablesUsing(
            fn (array $vars): \ArrayIterator => new \ArrayIterator([
                'kept' => $vars['in'],
                3      => 'integer key is dropped',
            ])
        );

        $this->assertSame(['kept' => 'v'], Native::applyTransformHook(['in' => 'v']));

        SerializableClosure::transformUseVariablesUsing(null);

        $this->assertNull(Native::$transformUseVariables);
    }
}
