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

namespace Tests\Unit\Signers;

use Omega\SerializableClosure\Signers\Hmac;
use Tests\TestCase;

final class HmacTest extends TestCase
{
    public function testSignReturnsThePayloadWithALowercaseHexHash(): void
    {
        $signature = (new Hmac('secret'))->sign('DATA');

        $this->assertArrayHasKey('serializable', $signature);
        $this->assertArrayHasKey('hash', $signature);
        $this->assertSame('DATA', $signature['serializable']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature['hash']);
    }

    public function testVerifyAcceptsASignatureProducedWithTheSameSecret(): void
    {
        $signer = new Hmac('secret');

        $this->assertTrue($signer->verify($signer->sign('payload')));
    }

    public function testVerifyRejectsASignatureProducedWithAnotherSecret(): void
    {
        $signed = (new Hmac('secret'))->sign('payload');

        $this->assertFalse((new Hmac('intruder'))->verify($signed));
    }

    public function testVerifyRejectsTamperedPayloads(): void
    {
        $signer = new Hmac('secret');
        $signature = $signer->sign('payload');
        $signature['serializable'] = 'PAYLOAD';

        $this->assertFalse($signer->verify($signature));
    }
}
