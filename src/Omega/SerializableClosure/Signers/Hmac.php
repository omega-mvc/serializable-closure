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
     * Creates a new signer instance.
     *
     * @param string $secret Holds the secret key to use for HMAC.
     */
    public function __construct(
        private readonly string $secret,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return array{serializable: string, hash: string} Return an array containing the signature.
     */
    public function sign(string $serialized): array
    {
        return [
            'serializable' => $serialized,
            'hash'         => hash_hmac('sha256', $serialized, $this->secret),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param array{serializable: string, hash: string} $signature Holds the signature to be verified.
     */
    public function verify(array $signature): bool
    {
        return hash_equals(
            hash_hmac('sha256', $signature['serializable'], $this->secret),
            $signature['hash']
        );
    }
}
