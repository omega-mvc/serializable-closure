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

namespace Omega\SerializableClosure\Serializers;

use Closure;
use Omega\SerializableClosure\Signers\SignerInterface;
use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;

use function call_user_func_array;
use function func_get_args;
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
class Signed implements SerializableInterface
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
    public function __invoke(): mixed
    {
        return call_user_func_array($this->closure, func_get_args());
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
     * @return array Return the serialized representation of the closure.
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
     * @param array $signature Holds the signature to verify and unserialize.
     * @return void
     * @throws InvalidSignatureException If the signature is invalid.
     */
    public function __unserialize(array $signature): void
    {
        if (static::$signer && ! static::$signer->verify($signature)) {
            throw new InvalidSignatureException();
        }

        /** @var SerializableInterface */
        $serializable = unserialize($signature['serializable']);

        $this->closure = $serializable->getClosure();
    }
}
