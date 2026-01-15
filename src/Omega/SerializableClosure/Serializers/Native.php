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
use DateTimeInterface;
use ReflectionException;
use ReflectionObject;
use stdClass;
use UnitEnum;
use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Support\ClosureScope;
use Omega\SerializableClosure\Support\ClosureStream;
use Omega\SerializableClosure\Support\ReflectionClosure;
use Omega\SerializableClosure\Support\SelfReference;
use Omega\SerializableClosure\UnsignedSerializableClosure;

use function call_user_func_array;
use function extract;
use function func_get_args;
use function is_array;
use function is_object;
use function spl_object_hash;

/**
 * Native class for serializing closures without signature verification.
 *
 * The `Native` class implements the SerializableInterface and provides
 * functionality for serializing and un serializing closures.
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
class Native implements SerializableInterface
{
    /**
     * Transform the use variables before serialization.
     *
     * @var Closure|null Holds the closure for transforming the variable before serialization or null.
     */
    public static ?Closure $transformUseVariables = null;

    /**
     * Resolve the use variables after deserialization.
     *
     * @var Closure|null Holds the closure for resolve the variable after deserialization or null.
     */
    public static ?Closure $resolveUseVariables = null;

    /**
     * The closure to be serialized/unserialize.
     *
     * @var Closure Holds the closure to be serialized/unserialize.
     */
    protected Closure $closure;

    /**
     * The closure's reflection.
     *
     * @var ReflectionClosure|null Holds the closure reflection or null.
     */
    protected ?ReflectionClosure $reflector = null;

    /**
     * The closure's code.
     *
     * @var array|string|null Holds the closure code or null.
     */
    protected array|string|null $code;

    /**
     * The closure's reference.
     *
     * @var string Holds the closure reference.
     */
    protected string $reference;

    /**
     * The closure's scope.
     *
     * @var ClosureScope|null Holds the closure scope or null.
     */
    protected ?ClosureScope $scope = null;

    /**
     * The "key" that marks an array as recursive.
     *
     * @var string ARRA_RECURSIVE_KEY Holds te key that marks an array as recursive.
     */
    public const ARRAY_RECURSIVE_KEY = 'OMEGACMS_SERIALIZABLE_RECURSIVE_KEY';

    /**
     * Creates a new serializable closure instance.
     *
     * @param Closure $closure Holds the closure object.
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
     * @return array Return an array of serializable representation of the closure.
     * @throws ReflectionException
     */
    public function __serialize(): array
    {
        if ($this->scope === null) {
            $this->scope = new ClosureScope();
            ++$this->scope->toSerialize;
        }

        ++$this->scope->serializations;

        $scope     = $object = null;
        $reflector = $this->getReflector();

        if ($reflector->isBindingRequired()) {
            $object = $reflector->getClosureThis();

            static::wrapClosures($object, $this->scope);
        }

        if ($scope = $reflector->getClosureScopeClass()) {
            $scope = $scope->name;
        }

        $this->reference = spl_object_hash($this->closure);

        $this->scope[$this->closure] = $this;

        $use = $reflector->getUseVariables();

        if (static::$transformUseVariables) {
            $use = call_user_func(static::$transformUseVariables, $reflector->getUseVariables());
        }

        $code = $reflector->getCode();

        $this->mapByReference($use);

        $data = [
            'use'      => $use,
            'function' => $code,
            'scope'    => $scope,
            'this'     => $object,
            'self'     => $this->reference,
        ];

        if (! --$this->scope->serializations && ! --$this->scope->toSerialize) {
            $this->scope = null;
        }

        return $data;
    }

    /**
     * Restore the closure after serialization.
     *
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        ClosureStream::register();

        $this->code = $data;
        unset($data);

        $this->code['objects'] = [];

        if ($this->code['use']) {
            $this->scope = new ClosureScope();

            if (static::$resolveUseVariables) {
                $this->code['use'] = call_user_func(static::$resolveUseVariables, $this->code['use']);
            }

            $this->mapPointers($this->code['use']);

            extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);

            $this->scope = null;
        }

        $this->closure = include ClosureStream::STREAM_PROTO . '://' . $this->code['function'];

        if ($this->code['this'] === $this) {
            $this->code['this'] = null;
        }

        $this->closure = $this->closure->bindTo($this->code['this'], $this->code['scope']);

        if (! empty($this->code['objects'])) {
            foreach ($this->code['objects'] as $item) {
                $item['property']->setValue($item['instance'], $item['object']->getClosure());
            }
        }

        $this->code = $this->code['function'];
    }

    /**
     * Ensures that the given closures are serializable, wrapping them with the appropriate class if needed.
     *
     * @param mixed        $data    Holds the data containing closures to be wrapped.
     * @param ClosureScope $storage Holds the closure storage instance.
     * @return void
     * @throws ReflectionException
     */
    public static function wrapClosures(mixed &$data, ClosureScope $storage): void
    {
        if ($data instanceof Closure) {
            $data = new static($data);
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }

            $data[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }
                static::wrapClosures($value, $storage);
            }

            unset($value, $data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof stdClass) {
            if (isset($storage[$data])) {
                $data = $storage[$data];

                return;
            }

            $data = $storage[$data] = clone $data;

            foreach ($data as &$value) {
                static::wrapClosures($value, $storage);
            }

            unset($value);
        } elseif (is_object($data) && ! $data instanceof static && ! $data instanceof UnitEnum) {
            if (isset($storage[$data])) {
                $data = $storage[$data];

                return;
            }

            $instance   = $data;
            $reflection = new ReflectionObject($instance);

            if (! $reflection->isUserDefined()) {
                $storage[$instance] = $data;

                return;
            }

            $storage[$instance] = $data = $reflection->newInstanceWithoutConstructor();

            do {
                if (! $reflection->isUserDefined()) {
                    break;
                }

                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || ! $property->getDeclaringClass()->isUserDefined()) {
                        continue;
                    }

                    $property->setAccessible(true);

                    if (PHP_VERSION >= 7.4 && ! $property->isInitialized($instance)) {
                        continue;
                    }

                    $value = $property->getValue($instance);

                    if (is_array($value) || is_object($value)) {
                        static::wrapClosures($value, $storage);
                    }

                    $property->setValue($data, $value);
                }
            } while ($reflection = $reflection->getParentClass());
        }
    }

    /**
     * Gets the closure's reflector.
     *
     * @return ReflectionClosure The reflection instance for the closure.
     * @throws ReflectionException
     */
    public function getReflector(): ReflectionClosure
    {
        if ($this->reflector === null) {
            $this->code      = null;
            $this->reflector = new ReflectionClosure($this->closure);
        }

        return $this->reflector;
    }

    /**
     * Internal method used to map closure pointers.
     *
     * @param mixed $data Holds the data to map pointers.
     * @return void
     */
    protected function mapPointers(mixed &$data): void
    {
        $scope = $this->scope;

        if ($data instanceof static) {
            $data = &$data->closure;
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }

            $data[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                } elseif ($value instanceof static) {
                    $data[$key] = &$value->closure;
                } elseif ($value instanceof SelfReference && $value->hash === $this->code['self']) {
                    $data[$key] = &$this->closure;
                } else {
                    $this->mapPointers($value);
                }
            }

            unset($value, $data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof stdClass) {
            if (isset($scope[$data])) {
                return;
            }

            $scope[$data] = true;

            foreach ($data as $key => &$value) {
                if ($value instanceof SelfReference && $value->hash === $this->code['self']) {
                    $data->{$key} = &$this->closure;
                } elseif (is_array($value) || is_object($value)) {
                    $this->mapPointers($value);
                }
            }

            unset($value);
        } elseif (is_object($data) && ! ( $data instanceof Closure )) {
            if (isset($scope[$data])) {
                return;
            }

            $scope[$data] = true;
            $reflection   = new ReflectionObject($data);

            do {
                if (! $reflection->isUserDefined()) {
                    break;
                }

                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || ! $property->getDeclaringClass()->isUserDefined()) {
                        continue;
                    }

                    $property->setAccessible(true);

                    if (PHP_VERSION >= 7.4 && ! $property->isInitialized($data)) {
                        continue;
                    }

                    if (PHP_VERSION >= 8.1 && $property->isReadOnly()) {
                        continue;
                    }

                    $item = $property->getValue($data);

                    if (
                        $item instanceof SerializableClosure
                        || $item instanceof UnsignedSerializableClosure
                        || ( $item instanceof SelfReference && $item->hash === $this->code['self'] )
                    ) {
                        $this->code['objects'][] = [
                            'instance' => $data,
                            'property' => $property,
                            'object'   => $item instanceof SelfReference ? $this : $item,
                        ];
                    } elseif (is_array($item) || is_object($item)) {
                        $this->mapPointers($item);
                        $property->setValue($data, $item);
                    }
                }
            } while ($reflection = $reflection->getParentClass());
        }
    }

    /**
     * Internal method used to map closures by reference within the data.
     *
     * @param mixed $data Holds the data to map by reference.
     * @return void
     * @throws ReflectionException
     */
    protected function mapByReference(mixed &$data): void
    {
        if ($data instanceof Closure) {
            if ($data === $this->closure) {
                $data = new SelfReference($this->reference);

                return;
            }

            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];

                return;
            }

            $instance = new static($data);

            $instance->scope = $this->scope;

            $data = $this->scope[$data] = $instance;
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }

            $data[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }

                $this->mapByReference($value);
            }

            unset($value, $data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof stdClass) {
            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];

                return;
            }

            $instance               = $data;
            $this->scope[$instance] = $data = clone $data;

            foreach ($data as &$value) {
                $this->mapByReference($value);
            }

            unset($value);
        } elseif (
            is_object($data)
            && ! $data instanceof SerializableClosure
            && ! $data instanceof UnsignedSerializableClosure
        ) {
            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];

                return;
            }

            $instance = $data;

            if ($data instanceof DateTimeInterface) {
                $this->scope[$instance] = $data;

                return;
            }

            if ($data instanceof UnitEnum) {
                $this->scope[$instance] = $data;

                return;
            }

            $reflection = new ReflectionObject($data);

            if (! $reflection->isUserDefined()) {
                $this->scope[$instance] = $data;

                return;
            }

            $this->scope[$instance] = $data = $reflection->newInstanceWithoutConstructor();

            do {
                if (! $reflection->isUserDefined()) {
                    break;
                }

                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || ! $property->getDeclaringClass()->isUserDefined()) {
                        continue;
                    }

                    $property->setAccessible(true);

                    if (PHP_VERSION >= 7.4 && ! $property->isInitialized($instance)) {
                        continue;
                    }

                    $value = $property->getValue($instance);

                    if (is_array($value) || is_object($value)) {
                        $this->mapByReference($value);
                    }

                    $property->setValue($data, $value);
                }
            } while ($reflection = $reflection->getParentClass());
        }
    }
}
