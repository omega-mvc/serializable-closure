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
use PhpToken;
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

use function array_pop;
use function count;
use function extract;
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
     * @var string ARRAY_RECURSIVE_KEY Holds the key that marks an array as recursive.
     */
    public const string ARRAY_RECURSIVE_KEY = 'OMEGACMS_SERIALIZABLE_RECURSIVE_KEY';

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
        if (! is_iterable($values)) {
            return [];
        }

        return self::stringKeyedEntries($values);
    }

    /**
     * Filters an iterable down to its string-keyed entries.
     *
     * @param iterable<mixed> $values Holds the value to filter.
     * @return array<string, mixed> Return an array containing only string-keyed entries.
     */
    private static function stringKeyedEntries(iterable $values): array
    {
        return array_filter(
            is_array($values) ? $values : iterator_to_array($values),
            is_string(...),
            ARRAY_FILTER_USE_KEY
        );
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
            return self::withStringKeys((static::$transformUseVariables)($uses));
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
            return self::withStringKeys((static::$resolveUseVariables)($uses));
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
            $bound  = self::wrapClosures($reflector->getClosureThis(), $closureScope);
            $object = is_object($bound) ? $bound : null;
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

        // The stream wrapper compiles the source as `return {source};`, so the
        // payload must be exactly one closure literal: anything else would turn
        // the include below into an arbitrary code-execution primitive.
        self::assertRestorableClosureSource($function);

        $this->code = $function;

        $deferredObjects = [];

        // extract() must run in an isolated scope: a hostile 'use' entry can
        // shadow any local variable it wants, and the values driving the
        // include and the bindTo() below must never be reachable from there.
        $reconstructed = $this->rebuildFromUse($use, $selfHash, $deferredObjects);

        if ($bound === $this) {
            $bound = null;
        }

        $this->closure = $reconstructed->bindTo($bound, $scope);

        foreach ($deferredObjects as $item) {
            $item['property']->setValue($item['instance'], $item['object']->getClosure());
        }
    }

    /**
     * Rebuilds the closure inside an isolated scope.
     *
     * The use variables have to be visible to the `use (...)` clause of the
     * reconstructed source, which is why they are extracted with references.
     * Delegating the extraction to a dedicated method — whose only reads after
     * the extract are the instance's own validated properties — keeps
     * attacker-controlled keys from shadowing $function, $scope, $bound or
     * $deferredObjects in __unserialize().
     *
     * @param array<string, mixed>                   $use             Holds the resolved use variables.
     * @param string                                 $selfHash        Holds the serialized self-reference hash.
     * @param array<int|string, DeferredBinding>     $deferredObjects Holds the deferred object bindings.
     * @return Closure Returns the reconstructed closure.
     * @throws ReflectionException If the source does not reconstruct to a closure.
     */
    private function rebuildFromUse(array $use, string $selfHash, array &$deferredObjects): Closure
    {
        if ($use !== []) {
            $this->scope = new ClosureScope();

            $use = static::applyResolveHook($use);

            $this->mapPointers($use, $selfHash, $deferredObjects);

            extract($use, EXTR_OVERWRITE | EXTR_REFS);

            $this->scope = null;
        }

        // Read after the extract() above: the payload source was validated as a
        // string in __unserialize(), and a hostile 'use' key must not be able to
        // shadow the local that feeds the include.
        $code = is_string($this->code) ? $this->code : '';

        $reconstructed = include ClosureStream::STREAM_PROTO . '://' . $code;

        if (! $reconstructed instanceof Closure) {
            throw new ReflectionException('Failed to reconstruct the closure from its serialized code.');
        }

        return $reconstructed;
    }

    /**
     * Verifies that the serialized source is a single closure literal.
     *
     * Two gates guard the include:
     *
     * 1. The very script the stream wrapper will compile (`return {source};`)
     *    must pass PHP's own parser; genuine compile errors keep surfacing as
     *    ParseError, exactly as the raw include used to raise them.
     * 2. A token walk shows one closure literal — optional attributes, static
     *    or by-reference modifiers included — followed by nothing but trivia.
     *    Statement separators at the top level are rejected, so payloads such
     *    as `fn() => 1; system(...)` or `function () {}()` cannot escape the
     *    expression context.
     *
     * @param string $source Holds the serialized closure source.
     * @return void
     * @throws \ParseError If the wrapped source is not valid PHP.
     * @throws ReflectionException If the source is not a single closure literal.
     */
    private static function assertRestorableClosureSource(string $source): void
    {
        PhpToken::tokenize('<?php return ' . $source . ';', TOKEN_PARSE);

        if (! self::isSingleClosureLiteral($source)) {
            throw new ReflectionException(
                'Failed to reconstruct the closure from its serialized code: '
                . 'the payload does not contain a single closure literal.'
            );
        }
    }

    /**
     * Checks that the source is exactly one closure literal.
     *
     * @param string $source Holds the serialized closure source.
     * @return bool Return true if the source is a single closure literal, false otherwise.
     */
    private static function isSingleClosureLiteral(string $source): bool
    {
        $stack   = [];
        $state   = 'head';
        $isArrow = false;

        // Tokens only exist inside PHP tags: wrap the source and skip the
        // synthetic opening tag, mirroring how the stream wrapper compiles it.
        $started = false;

        foreach (PhpToken::tokenize('<?php ' . $source) as $token) {
            if (! $started) {
                $started = true;

                continue;
            }
            if ($token->is(T_WHITESPACE) || $token->is(T_COMMENT) || $token->is(T_DOC_COMMENT)) {
                continue;
            }

            $text = $token->text;

            if ($state === 'done') {
                return false;
            }

            if ($state === 'head' && $stack === []) {
                if ($token->is(T_STATIC) || $text === '&') {
                    continue;
                }

                if ($token->is(T_ATTRIBUTE)) {
                    $stack[] = $text;

                    continue;
                }

                if ($token->is(T_FUNCTION)) {
                    $state = 'function';

                    continue;
                }

                if ($token->is(T_FN)) {
                    $isArrow = true;
                    $state   = 'params';

                    continue;
                }

                return false;
            }

            if ($state === 'function') {
                if ($text === '&') {
                    continue;
                }

                if ($text === '(') {
                    $stack[] = $text;
                    $state   = 'params';

                    continue;
                }

                return false;
            }

            if (
                $token->is(T_ATTRIBUTE)
                || $token->is(T_CURLY_OPEN)
                || $token->is(T_DOLLAR_OPEN_CURLY_BRACES)
                || $text === '('
                || $text === '['
                || $text === '{'
            ) {
                $stack[] = $text;

                if (! $isArrow && $text === '{' && count($stack) === 1) {
                    $state = 'body';
                }

                continue;
            }

            if ($text === ')' || $text === ']' || $text === '}') {
                if ($stack === []) {
                    return false;
                }

                array_pop($stack);

                if (! $isArrow && $text === '}' && $stack === [] && $state === 'body') {
                    $state = 'done';
                }

                continue;
            }

            if ($stack === []) {
                if ($isArrow && $text === ';') {
                    $state = 'done';

                    continue;
                }

                if ($text === ';' || $text === ',') {
                    return false;
                }
            }
        }

        return $state === 'done' || ($state === 'params' && $isArrow && $stack === []);
    }

    /**
     * Ensures that the given closures are serializable, wrapping them with the
     * appropriate class if needed, and returns the transformed value.
     *
     * @param mixed        $data    Holds the data whose closures have to be wrapped.
     * @param ClosureScope $storage Holds the closure storage instance.
     * @return mixed Return the value with every closure wrapped for serialization.
     * @throws ReflectionException
     */
    private static function wrapClosures(mixed $data, ClosureScope $storage): mixed
    {
        if ($data instanceof Closure) {
            return new static($data);
        }

        if (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return $data;
            }

            $data[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }
                $value = self::wrapClosures($value, $storage);
            }

            unset($value, $data[self::ARRAY_RECURSIVE_KEY]);

            return $data;
        }

        if ($data instanceof stdClass) {
            if (isset($storage[$data])) {
                return $storage[$data];
            }

            $clone = clone $data;

            $storage[$data] = $clone;

            foreach (array_keys((array) $clone) as $key) {
                $item = &$clone->{$key};

                $item = self::wrapClosures($item, $storage);

                unset($item);
            }

            return $clone;
        }

        if (
            is_object($data)
            && ! $data instanceof static
            && ! $data instanceof UnitEnum
        ) {
            if (isset($storage[$data])) {
                return $storage[$data];
            }

            $reflection = new ReflectionObject($data);

            if (! $reflection->isUserDefined()) {
                $storage[$data] = $data;

                return $data;
            }

            $freshInstance = $reflection->newInstanceWithoutConstructor();

            $storage[$data] = $freshInstance;

            foreach (self::userDefinedProperties($data) as $property => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = self::wrapClosures($value, $storage);
                }

                $property->setValue($freshInstance, $value);
            }

            return $freshInstance;
        }

        return $data;
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
