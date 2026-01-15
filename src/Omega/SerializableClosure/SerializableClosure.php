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

namespace Omega\SerializableClosure;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Serializers\Signed;
use Omega\SerializableClosure\Serializers\SerializableInterface;
use Omega\SerializableClosure\Signers\Hmac;

use function call_user_func_array;
use function func_get_args;
use function get_class;
use function is_object;
use function serialize;

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
     * @param Closure $closure Holds the current closure object.
     * @return void
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
     * @return mixed
     */
    public function __invoke(): mixed
    {
        return call_user_func_array($this->serializable, func_get_args());
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
     * @return UnsignedSerializableClosure Return a new instance of UnsignedSerializableClosure.
     */
    public static function unsigned(Closure $closure): UnsignedSerializableClosure
    {
        return new UnsignedSerializableClosure($closure);
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param string|null $secret Holds the secret code to set.
     * @return void
     */
    public static function setSecretKey(?string $secret): void
    {
        Signed::$signer = $secret
            ? new Hmac($secret)
            : null;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param Closure|null $transformer Holds the current closure instance for transformer.
     * @return void
     */
    public static function transformUseVariablesUsing(?Closure $transformer): void
    {
        Native::$transformUseVariables = $transformer;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param Closure|null $resolver Holds the current closure instance for resolver.
     * @return void
     */
    public static function resolveUseVariablesUsing(?Closure $resolver): void
    {
        Native::$resolveUseVariables = $resolver;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array Return an array of the serialized representation of the closure.
     * @throws ReflectionException
     */
    public function __serialize(): array
    {
        $closure = $this->serializable->getClosure();

        // Check if the closure contains an anonymous class
        $reflectionFunction = new ReflectionFunction($closure);
        $uses               = $reflectionFunction->getStaticVariables();

        foreach ($uses as $variable => $value) {
            if (is_object($value) && $this->isAnonymousClass($value)) {
                // Handle anonymous class serialization
                $uses[$variable] = $this->serializeAnonymousClass($value);
            }
        }

        return [
            'serializable' => $this->serializable,
            'uses'         => $uses,
        ];
    }

    /**
     * Restore the closure after serialization.
     *
     * @param array $data Holds an array of the closure data for restore.
     * @return void
     * @throws ReflectionException
     */
    public function __unserialize(array $data): void
    {
        $this->serializable = $data['serializable'];

        // Handle anonymous class deserialization
        $uses = $data['uses'];

        foreach ($uses as $variable => $value) {
            if (is_array($value) && $value['__anonymous_class'] ?? false) {
                // Restore the anonymous class instance
                $uses[$variable] = $this->unserializeAnonymousClass($value);
            }
        }

        // Set the static variables of the closure
        $this->setClosureUses($uses);
    }

    /**
     * Check if an object is an anonymous class.
     *
     * @param object $object Holds the class object to check.
     * @return bool Return true if the object is anonymous class, false if not.
     */
    protected function isAnonymousClass(object $object): bool
    {
        return ( new ReflectionClass($object) )->isAnonymous();
    }

    /**
     * Serialize a anonymous class.
     *
     * @param object $object Holds the anonymous class to serialize.
     * @return array Return an array of serialize anonymous class.
     */
    protected function serializeAnonymousClass(object $object): array
    {
        // Customize the serialization of the anonymous class as needed
        return [
            '__anonymous_class' => true,
            '__class_name'      => get_class($object),
            '__class_data'      => serialize($object),
        ];
    }

    /**
     * Unserialize an anonymous class.
     *
     * @param array $data Holds an array of anonymous class to unserialize.
     * @return object Return the unserialize object class for class.
     */
    protected function unserializeAnonymousClass(array $data): object
    {
        // Customize the deserialization of the anonymous class as needed.
        return unserialize($data['__class_data']);
    }

    /**
     * Set the static variables of the closure.
     *
     * @param array $uses Holds an array of use variables associated with the closure.
     * @return void
     * @throws ReflectionException
     */
    protected function setClosureUses(array $uses): void
    {
        $reflectionFunction = new ReflectionFunction($this->serializable->getClosure());

        // Use ReflectionFunction to set the static variables.
        $closureThis = $reflectionFunction->getClosureThis();

        if ($closureThis !== null) {
            $closureThis->uses = $uses;
        }
    }
}
