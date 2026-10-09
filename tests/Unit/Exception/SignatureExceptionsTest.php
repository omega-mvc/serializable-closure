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

namespace Tests\Unit\Exception;

use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;
use Tests\TestCase;

final class SignatureExceptionsTest extends TestCase
{
    public function testInvalidSignatureExceptionCarriesADefaultMessage(): void
    {
        $this->assertNotSame('', (new InvalidSignatureException())->getMessage());
    }

    public function testMissingSecretKeyExceptionCarriesADefaultMessage(): void
    {
        $this->assertStringContainsString('secret key', (new MissingSecretKeyException())->getMessage());
    }

    public function testBothExceptionsAcceptACustomMessage(): void
    {
        $this->assertSame('custom', (new InvalidSignatureException('custom'))->getMessage());
        $this->assertSame('custom', (new MissingSecretKeyException('custom'))->getMessage());
    }
}
