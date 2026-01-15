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
 * Signer interface.
 *
 * The `SignerInterface` defines methods for signing and verifying serialized closures.
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
interface SignerInterface
{
    /**
     * Sign the given serializable data.
     *
     * @param string $serialized Holds the serializable data to be signed.
     * @return array Return an array containing the signature.
     */
    public function sign(string $serialized): array;

    /**
     * Verify the given signature.
     *
     * @param array $signature Holds the signature to be verified.
     * @return bool Return true if the signature is valid, false otherwise.
     */
    public function verify(array $signature): bool;
}
