<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.3
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

namespace Omega\SerializableClosure\Signers;

/**
 * Hmac class.
 *
 * The `Hmac` class implements the SignerInterface for signing and verifying serialized
 * closures using HMAC.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Signers
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
class Hmac implements SignerInterface
{
    /**
     * The secret key.
     *
     * @var string Holds the secret string.
     */
    protected string $secret;

    /**
     * Creates a new signer instance.
     *
     * @param string $secret Holds the secret key to use for HMAC.
     * @return void
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public function sign(string $serialized): array
    {
        return [
            'serializable' => $serialized,
            'hash'         => base64_encode(hash_hmac('sha256', $serialized, $this->secret, true)),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function verify(array $signature): bool
    {
        return hash_equals(base64_encode(
            hash_hmac('sha256', $signature['serializable'], $this->secret, true)
        ), $signature['hash']);
    }
}
