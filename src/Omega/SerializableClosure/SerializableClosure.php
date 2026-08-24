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

namespace Omega\SerializableClosure;

use Closure;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\Serializers\SerializableInterface;
use Omega\SerializableClosure\Signers\Hmac;

/**
 * Serializable closure class.
 *
 * The `SerializableClosure` class provides a flexible mechanism for serializing closures
 * with the option to use cryptographic signatures for integrity verification. This class
 * supports both signed and unsigned closures. The signed closures utilize HMAC (Hash-based
 * Message Authentication Code) for signature generation.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
class SerializableClosure
{
    /**
     * The closure's serializable.
     *
     * @var SerializableInterface Holds the closure's serializable.
     */
    protected SerializableInterface $serializable;

    /**
     * Creates a new serializable closure instance.
     *
     * If a secret key has been set with setSecretKey() the closure is handled
     * by the Signed serializer, otherwise the Native one is used.
     *
     * @param Closure $closure Holds the current closure object.
     */
    public function __construct(Closure $closure)
    {
        $this->serializable = Signed::$signer
            ? new Signed($closure)
            : new Native($closure);
    }

    /**
     * Resolve the closure with the given arguments.
     *
     * @param mixed ...$args Holds the arguments to pass to the closure.
     * @return mixed Return the closure result.
     */
    public function __invoke(mixed ...$args): mixed
    {
        return ($this->serializable)(...$args);
    }

    /**
     * Gets the closure.
     *
     * @return Closure Return the current closure object.
     */
    public function getClosure(): Closure
    {
        return $this->serializable->getClosure();
    }

    /**
     * Create a new unsigned serializable closure instance.
     *
     * @param Closure $closure Holds the current closure instance.
     */
    public static function unsigned(Closure $closure): UnsignedSerializableClosure
    {
        return new UnsignedSerializableClosure($closure);
    }

    /**
     * Sets the serializable closure secret key.
     *
     * When a secret key is set every new SerializableClosure instance uses the
     * Signed serializer; setting it back to null restores native serialization.
     *
     * @param string|null $secret Holds the secret code to set.
     */
    public static function setSecretKey(?string $secret): void
    {
        Signed::$signer = $secret
            ? new Hmac($secret)
            : null;
    }

    /**
     * Sets the closure used to transform captured variables before serialization.
     *
     * @param Closure|null $transformer Holds the transformation closure or null to unset.
     */
    public static function transformUseVariablesUsing(?Closure $transformer): void
    {
        Native::$transformUseVariables = $transformer;
    }

    /**
     * Sets the closure used to resolve captured variables after deserialization.
     *
     * @param Closure|null $resolver Holds the resolution closure or null to unset.
     */
    public static function resolveUseVariablesUsing(?Closure $resolver): void
    {
        Native::$resolveUseVariables = $resolver;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array{serializable: SerializableInterface}
     *               Return an array of the serialized representation of the closure.
     */
    public function __serialize(): array
    {
        return [
            'serializable' => $this->serializable,
        ];
    }

    /**
     * Restore the closure after serialization.
     *
     * @param array{serializable: SerializableInterface} $data Holds an array of the closure data for restore.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->serializable = $data['serializable'];
    }
}
