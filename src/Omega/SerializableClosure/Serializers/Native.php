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
use DateTimeInterface;
use Generator;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;
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
 *
 * @phpstan-type BindingTarget Native|SerializableClosure|UnsignedSerializableClosure
 * @phpstan-type DeferredBinding array{instance: object, property: ReflectionProperty, object: BindingTarget}
 */
final class Native implements SerializableInterface
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
     * The closure to be serialized/unserialize. Null while the closure is
     * being reconstructed during unserialization.
     *
     * @var Closure|null Holds the closure to be serialized/unserialize or null.
     */
    protected ?Closure $closure = null;

    /**
     * The closure's reflection.
     *
     * @var ReflectionClosure|null Holds the closure reflection or null.
     */
    protected ?ReflectionClosure $reflector = null;

    /**
     * The closure's code. During unserialization this is the raw payload array;
     * once restored it holds the closure's function source, or null.
     *
     * @var array<string, mixed>|string|null Holds the closure code or null.
     */
    protected array|string|null $code = null;

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
    public function __invoke(mixed ...$args): mixed
    {
        return ($this->getClosure())(...$args);
    }

    /**
     * Filters a value down to its string-keyed entries, for use-variable maps.
     *
     * @param mixed $values Holds the value to filter.
     * @return array<string, mixed> Return an array containing only string-keyed entries.
     */
    private static function withStringKeys(mixed $values): array
    {
        $filtered = [];

        if (! is_iterable($values)) {
            return $filtered;
        }

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Applies the registered transformation hook to the given use variables.
     *
     * @param array<string, mixed> $uses Holds the raw use variables.
     * @return array<string, mixed> Return the transformed use variables.
     */
    public static function applyTransformHook(array $uses): array
    {
        if (static::$transformUseVariables instanceof Closure) {
            return self::withStringKeys(call_user_func(static::$transformUseVariables, $uses));
        }

        return $uses;
    }

    /**
     * Applies the registered resolution hook to the given use variables.
     *
     * @param array<string, mixed> $uses Holds the transformed use variables.
     * @return array<string, mixed> Return the resolved use variables.
     */
    public static function applyResolveHook(array $uses): array
    {
        if (static::$resolveUseVariables instanceof Closure) {
            return self::withStringKeys(call_user_func(static::$resolveUseVariables, $uses));
        }

        return $uses;
    }

    /**
     * {@inheritdoc}
     */
    public function getClosure(): Closure
    {
        if (! $this->closure instanceof Closure) {
            throw new ReflectionException('The closure has not been constructed or reconstructed yet.');
        }

        return $this->closure;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array{
     *     use: array<string, mixed>,
     *     function: string,
     *     scope: class-string|null,
     *     this: object|null,
     *     self: string
     * } Return an array of serializable representation of the closure.
     * @throws ReflectionException
     */
    public function __serialize(): array
    {
        if ($this->scope === null) {
            $this->scope = new ClosureScope();
            ++$this->scope->toSerialize;
        }

        $closureScope = $this->scope;

        $closureScope->beginSerialization();

        $object  = null;
        $scope   = null;
        $closure = $this->getClosure();

        $reflector = $this->getReflector();

        if ($reflector->isBindingRequired()) {
            $wrappedThis = $reflector->getClosureThis();

            static::wrapClosures($wrappedThis, $closureScope);

            $object = is_object($wrappedThis) ? $wrappedThis : null;
        }

        if ($scopeClass = $reflector->getClosureScopeClass()) {
            $scope = $scopeClass->name;
        }

        $this->reference = spl_object_hash($closure);

        $closureScope[$closure] = $this;

        $use = $reflector->getUseVariables();

        $use = static::applyTransformHook($use);

        $code = $reflector->getCode();

        $mappedUse = $use;

        $this->mapByReference($mappedUse);

        $use = self::withStringKeys($mappedUse);

        $data = [
            'use'      => $use,
            'function' => $code,
            'scope'    => $scope,
            'this'     => $object,
            'self'     => $this->reference,
        ];

        if ($closureScope->finishSerialization()) {
            $this->scope = null;
        }

        return $data;
    }

    /**
     * Restore the closure after serialization.
     *
     * @param array<string, mixed> $data Holds the closure data for restore.
     * @return void
     * @throws ReflectionException If the closure cannot be reconstructed from its code.
     */
    public function __unserialize(array $data): void
    {
        ClosureStream::register();

        $use      = [];
        $function = is_string($data['function'] ?? null) ? $data['function'] : '';
        $scope    = null;
        $bound    = is_object($data['this'] ?? null) ? $data['this'] : null;
        $selfHash = is_string($data['self'] ?? null) ? $data['self'] : '';

        if (is_array($data['use'] ?? null)) {
            foreach ($data['use'] as $varName => $varValue) {
                if (is_string($varName)) {
                    $use[$varName] = $varValue;
                }
            }
        }

        if (is_string($data['scope'] ?? null) && (class_exists($data['scope']) || interface_exists($data['scope']))) {
            $scope = $data['scope'];
        }

        $this->code = $function;

        $deferredObjects = [];

        if ($use !== []) {
            $this->scope = new ClosureScope();

            $use = static::applyResolveHook($use);

            $this->mapPointers($use, $selfHash, $deferredObjects);


            extract($use, EXTR_OVERWRITE | EXTR_REFS);


            $this->scope = null;
        }

        $reconstructed = include ClosureStream::STREAM_PROTO . '://' . $function;


        if (! $reconstructed instanceof Closure) {
            throw new ReflectionException('Failed to reconstruct the closure from its serialized code.');
        }

        if ($bound === $this) {
            $bound = null;
        }

        $this->closure = $reconstructed->bindTo($bound, $scope);


        foreach ($deferredObjects as $item) {
            $item['property']->setValue($item['instance'], $item['object']->getClosure());
        }
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

            $clone = clone $data;

            $storage[$data] = $clone;
            $data           = $clone;

            foreach (array_keys((array) $data) as $key) {
                $value = &$data->{$key};

                static::wrapClosures($value, $storage);

                unset($value);
            }
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

            $freshInstance = $reflection->newInstanceWithoutConstructor();

            $storage[$instance] = $freshInstance;
            $data               = $freshInstance;

            foreach (self::userDefinedProperties($instance) as $property => $value) {
                if (is_array($value) || is_object($value)) {
                    static::wrapClosures($value, $storage);
                }

                $property->setValue($data, $value);
            }
        }
    }

    /**
     * Iterates the initialized, non-static properties declared by user-defined
     * classes along the whole inheritance chain of the given instance.
     *
     * @param object $instance Holds the object whose properties to visit.
     * @return Generator<ReflectionProperty, mixed, null, void> Yields property/value pairs.
     */
    private static function userDefinedProperties(object $instance): Generator
    {
        $reflection = new ReflectionObject($instance);

        while ($reflection->isUserDefined()) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || ! $property->getDeclaringClass()->isUserDefined()) {
                    continue;
                }

                if (! $property->isInitialized($instance)) {
                    continue;
                }

                yield $property => $property->getValue($instance);
            }

            $parent = $reflection->getParentClass();

            if ($parent === false) {
                break;
            }

            $reflection = $parent;
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
            if (! $this->closure instanceof Closure) {
                throw new ReflectionException('No closure available to reflect.');
            }

            $this->code      = null;
            $this->reflector = new ReflectionClosure($this->closure);
        }

        return $this->reflector;
    }

    /**
     * Maps SelfReference pointers inside the use variables onto the closure
     * being reconstructed.
     *
     * @param array<array-key, mixed> $data
     *                                          Holds the use variables to map pointers for.
     * @param string              $selfHash        Holds the serialized self-reference hash.
     * @param array<int|string, DeferredBinding> $deferredObjects
     *                                          Holds the deferred object bindings discovered while mapping.
     * @return void
     */
    protected function mapPointers(array &$data, string $selfHash, array &$deferredObjects): void
    {
        $scope = $this->scope;

        if (! $scope instanceof ClosureScope) {
            return;
        }

        foreach ($data as $key => &$value) {
            if ($key === self::ARRAY_RECURSIVE_KEY) {
                continue;
            } elseif ($value instanceof static) {
                $data[$key] = &$value->closure;

                continue;
            } elseif ($value instanceof SelfReference && $value->hash === $selfHash) {
                $data[$key] = &$this->closure;

                continue;
            }

            $this->mapPointersValue($data[$key], $selfHash, $deferredObjects, $scope);
        }
    }

    /**
     * Internal walker mapping pointer slots for a single use-variable value.
     *
     * @param mixed             $value           Holds the value to map pointers for.
     * @param string            $selfHash        Holds the serialized self-reference hash.
     * @param array<int|string, DeferredBinding> $deferredObjects
     *                                           Holds the deferred object bindings discovered while mapping.
     * @param ClosureScope      $scope           Holds the current closure scope.
     * @return void
     */
    private function mapPointersValue(
        mixed &$value,
        string $selfHash,
        array &$deferredObjects,
        ClosureScope $scope
    ): void {
        if (is_array($value)) {
            if (isset($value[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }

            $value[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($value as $key => &$item) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                } elseif ($item instanceof static) {
                    $value[$key] = &$item->closure;
                } elseif ($item instanceof SelfReference && $item->hash === $selfHash) {
                    $value[$key] = &$this->closure;
                } else {
                    $this->mapPointersValue($item, $selfHash, $deferredObjects, $scope);
                }
            }

            unset($item, $value[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($value instanceof stdClass) {
            if (isset($scope[$value])) {
                return;
            }

            $scope[$value] = true;

            foreach (array_keys((array) $value) as $key) {
                $item = &$value->{$key};

                if ($item instanceof SelfReference && $item->hash === $selfHash) {
                    $value->{$key} = &$this->closure;
                } elseif (is_array($item) || is_object($item)) {
                    $this->mapPointersValue($item, $selfHash, $deferredObjects, $scope);
                }

                unset($item);
            }
        } elseif (is_object($value) && ! ( $value instanceof Closure )) {
            if (isset($scope[$value])) {
                return;
            }

            $scope[$value] = true;
            $reflection    = new ReflectionObject($value);

            do {
                if (! $reflection->isUserDefined()) {
                    break;
                }

                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || ! $property->getDeclaringClass()->isUserDefined()) {
                        continue;
                    }

                    if (! $property->isInitialized($value)) {
                        continue;
                    }

                    if ($property->isReadOnly()) {
                        continue;
                    }

                    $item = $property->getValue($value);

                    if (
                        $item instanceof SerializableClosure
                        || $item instanceof UnsignedSerializableClosure
                        || ( $item instanceof SelfReference && $item->hash === $selfHash )
                    ) {
                        $deferredObjects[] = [
                            'instance' => $value,
                            'property' => $property,
                            'object'   => $item instanceof SelfReference ? $this : $item,
                        ];
                    } elseif (is_array($item) || is_object($item)) {
                        $this->mapPointersValue($item, $selfHash, $deferredObjects, $scope);
                        $property->setValue($value, $item);
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
        $scope = $this->scope;

        if (! $scope instanceof ClosureScope) {
            return;
        }

        if ($data instanceof Closure) {
            if ($data === $this->closure) {
                $data = new SelfReference($this->reference);

                return;
            }

            if (isset($scope[$data])) {
                $data = $scope[$data];

                return;
            }

            $instance = new static($data);

            $instance->scope = $scope;

            $scope[$data] = $instance;
            $data         = $instance;
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
            if (isset($scope[$data])) {
                $data = $scope[$data];

                return;
            }

            $clone = clone $data;

            $scope[$data] = $clone;
            $data         = $clone;

            foreach (array_keys((array) $data) as $key) {
                $value = &$data->{$key};

                $this->mapByReference($value);

                unset($value);
            }
        } elseif (
            is_object($data)
            && ! $data instanceof SerializableClosure
            && ! $data instanceof UnsignedSerializableClosure
            && ! $data instanceof Native
            && ! $data instanceof SelfReference
        ) {
            if (isset($scope[$data])) {
                $data = $scope[$data];

                return;
            }

            $instance = $data;

            if ($data instanceof DateTimeInterface) {
                $scope[$instance] = $data;

                return;
            }

            if ($data instanceof UnitEnum) {
                $scope[$instance] = $data;

                return;
            }

            $reflection = new ReflectionObject($data);

            if (! $reflection->isUserDefined()) {
                $scope[$instance] = $data;

                return;
            }

            $freshInstance = $reflection->newInstanceWithoutConstructor();

            $scope[$instance] = $freshInstance;
            $data             = $freshInstance;

            foreach (self::userDefinedProperties($instance) as $property => $value) {
                if (is_array($value) || is_object($value)) {
                    $this->mapByReference($value);
                }

                $property->setValue($data, $value);
            }
        }
    }
}
