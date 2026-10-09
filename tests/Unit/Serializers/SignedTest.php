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

namespace Tests\Unit\Serializers;

use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\Signers\Hmac;
use stdClass;
use Tests\TestCase;

final class SignedTest extends TestCase
{
    public function testSerializingWithoutASignerThrows(): void
    {
        Signed::$signer = null;

        $signed = new Signed(fn (): int => 1);

        $this->expectException(MissingSecretKeyException::class);
        $signed->__serialize();
    }

    public function testSerializingWithASignerReturnsTheSignedEnvelope(): void
    {
        Signed::$signer = new Hmac('secret');

        $data = (new Signed(fn (): int => 1))->__serialize();

        $this->assertArrayHasKey('serializable', $data);
        $this->assertArrayHasKey('hash', $data);
    }

    public function testUnserializingWithoutASignerIsRejectedFailClosed(): void
    {
        Signed::$signer = new Hmac('secret');
        $signature = (new Signed(fn (): int => 1))->__serialize();
        Signed::$signer = null;

        $this->expectException(MissingSecretKeyException::class);
        (new Signed(fn (): int => 0))->__unserialize($signature);
    }

    public function testUnserializingWithAWrongSignerIsRejected(): void
    {
        Signed::$signer = new Hmac('right');
        $signature = (new Signed(fn (): int => 1))->__serialize();
        Signed::$signer = new Hmac('wrong');

        $this->expectException(InvalidSignatureException::class);
        (new Signed(fn (): int => 0))->__unserialize($signature);
    }

    public function testUnserializingAnEnvelopeWhosePayloadIsNotSerializableIsRejected(): void
    {
        // A validly-signed envelope whose inner payload unserializes to something
        // that is not a SerializableInterface implementation.
        $hmac = new Hmac('secret');
        $forged = $hmac->sign(serialize(new stdClass()));

        Signed::$signer = $hmac;

        $this->expectException(InvalidSignatureException::class);
        (new Signed(fn (): int => 0))->__unserialize($forged);
    }

    public function testUnserializingAValidEnvelopeRestoresTheClosure(): void
    {
        Signed::$signer = new Hmac('secret');

        $envelope = (new Signed(fn (): int => 3))->__serialize();
        $restored = new Signed(fn (): int => 0);
        $restored->__unserialize($envelope);

        $this->assertSame(3, $restored->getClosure()());
        $this->assertSame(3, $restored());
    }
}
