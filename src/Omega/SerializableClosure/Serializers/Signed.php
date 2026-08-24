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

namespace Omega\SerializableClosure\Serializers;

use Closure;
use Omega\SerializableClosure\Signers\SignerInterface;
use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;

use function serialize;
use function unserialize;

/**
 * Signed class for serializable closures with signature verification.
 *
 * This class implements the SerializableInterface and adds functionality
 * to sign and verify the signature of the closure during serialization.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Serializers
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
final class Signed implements SerializableInterface
{
    /**
     * The signer that will sign and verify the closure's signature.
     *
     * @var SignerInterface|null Holds the current signer object or null.
     */
    public static ?SignerInterface $signer = null;

    /**
     * The closure to be serialized/unserialize.
     *
     * @var Closure Holds the closure to be serialized/unserialize.
     */
    protected Closure $closure;

    /**
     * Creates a new serializable closure instance.
     *
     * @param Closure $closure Holds the closure to be serialized/unserialize.
     * @return void
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(mixed ...$args): mixed
    {
        return ($this->closure)(...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function getClosure(): Closure
    {
        return $this->closure;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array{serializable: string, hash: string} Return the serialized representation of the closure.
     * @throws MissingSecretKeyException If no signer is specified.
     */
    public function __serialize(): array
    {
        if (! static::$signer) {
            throw new MissingSecretKeyException();
        }

        return static::$signer->sign(
            serialize(new Native($this->closure))
        );
    }

    /**
     * Restore the closure after serialization.
     *
     * @param array{serializable: string, hash: string} $signature Holds the signature to verify and unserialize.
     * @return void
     * @throws MissingSecretKeyException If no signer is set when loading signed data.
     * @throws InvalidSignatureException If the signature is invalid.
     */
    public function __unserialize(array $signature): void
    {
        if (! static::$signer instanceof SignerInterface) {
            throw new MissingSecretKeyException();
        }

        if (! static::$signer->verify($signature)) {
            throw new InvalidSignatureException();
        }

        $serializable = unserialize($signature['serializable']);

        if (! $serializable instanceof SerializableInterface) {
            throw new InvalidSignatureException();
        }

        $this->closure = $serializable->getClosure();
    }
}
