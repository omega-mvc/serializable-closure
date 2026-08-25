<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture file for exercising every reachable uncovered branch in
 * ReflectionClosure::getCode() and fetchItems(). Placed at file-level
 * so the tokenizer processes the full file.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;

/**
 * Named function at file scope — triggers the named_function path
 * in the getCode() state machine (T_STRING after T_FUNCTION).
 */
function tokenizerNamedFunction(): string
{
    return 'named';
}

/**
 * Named function at file scope — exercises imported function resolution
 * in the getCode() id_name state.
 *
 * @param list<int> $items
 * @return list<int>
 */
function tokenizerArraySort(array $items): array
{
    sort($items);

    return $items;
}

/**
 * Class providing closures that exercise the getCode() state-machine edges.
 */
class TokenizerEdgeCases
{
    /** Static property for self::$prop resolution. */
    public static int $counter = 0;

    /** Static method returning closure using self::$prop. */
    public static function staticPropClosure(): \Closure
    {
        return function (): int {
            return self::$counter;
        };
    }

    /** Closure using self::class — hits T_CLASS_C inside closure body. */
    public function selfClassClosure(): \Closure
    {
        return function (): string {
            return self::class;
        };
    }

    /** Closure with qualified type in params, return type, and body. */
    public function qualifiedNames(): \Closure
    {
        return function (Closure\Subspace\QF $x): Closure\Subspace\QF {
            $result = new Closure\Subspace\QF();
            return $x;
        };
    }

    /** Closure using `use` clause — hits T_USE in closure_args state. */
    public function useClause(int $a): \Closure
    {
        $b = 2;
        return function () use ($a, $b): int {
            return $a + $b;
        };
    }

    /** Closure using short arrow with T_DOUBLE_ARROW in body. */
    public function arrowWithSpread(): \Closure
    {
        $data = [1, 2, 3];
        return fn (): array => [...$data];
    }

    /** Static arrow closure with return type using T_NAME_QUALIFIED. */
    public static function staticArrowQualified(): \Closure
    {
        return static fn (): Closure\Subspace\QF => new Closure\Subspace\QF();
    }

    /** Closure with anonymous class extending a string name. */
    public function anonymousClassExtends(): \Closure
    {
        return function (): \Closure {
            $obj = new class extends \stdClass {
                public function greet(): string
                {
                    return 'hello';
                }
            };
            return function () use ($obj): string {
                return $obj->greet();
            };
        };
    }

    /** Closure using new self() and new parent(). */
    public function newSelfParent(): \Closure
    {
        return function (): self {
            return new self();
        };
    }

    /** Closure using static:: in static method context. */
    public static function staticSelf(): \Closure
    {
        return static function (): string {
            return static::class;
        };
    }

    /** Closure that chains method calls — exercises ignore_next state. */
    public function chainedCalls(): \Closure
    {
        return function (): string {
            return strtolower(trim('  HI  '));
        };
    }

    /** Closure using instanceof — exercises the instanceof context. */
    public function instanceofCheck(): \Closure
    {
        return function (mixed $x): bool {
            return $x instanceof \stdClass;
        };
    }

    /** Closure using a function from the use-group (closure body function call). */
    public function useGroupFunctionCall(): \Closure
    {
        return function (): string {
            return tokenizerNamedFunction();
        };
    }

    /** Closure calling a function via namespace-qualified name. */
    public function namespaceQualifiedCall(): \Closure
    {
        return function (): string {
            return \Tests\Fixtures\tokenizerNamedFunction();
        };
    }

    /** Closure with name-qualified identifier directly in body (not after new/use/instanceof). */
    public function nameQualifiedInBody(): \Closure
    {
        return function (): string {
            return \Tests\Fixtures\Colorable::class;
        };
    }

    /** Closure containing an anonymous class that uses a trait. */
    public function anonymousClassWithTrait(): \Closure
    {
        return function (): \Closure {
            $obj = new class {
                use \Tests\Fixtures\Colorable;

                public function color(): string
                {
                    return $this->color;
                }
            };

            return function () use ($obj): string {
                return $obj->color();
            };
        };
    }

    /** Closure calling an imported function (exercises function resolution in id_name). */
    public function importedFunctionCall(): \Closure
    {
        $data = [3, 1, 2];

        return function () use ($data): array {
            return \Tests\Fixtures\tokenizerArraySort($data);
        };
    }

    /**
     * Same-line named function forces the function/named_function reset
     * paths before the real closure is reached.
     */
    public function namedFunctionSameLine(): \Closure
    {
        function tokenizerEdgeProbeA1() {} return function () {
            return 1;
        };
    }

    /** Named function followed by an arrow closure resets through T_FN. */
    public function namedFunctionBeforeArrow(): \Closure
    {
        function tokenizerEdgeProbeB2() {} return fn () => 3;
    }

    /** T_STATIC followed by :: resets the hunt back to start. */
    public function staticQualifiedReset(): \Closure
    {
        return false ? static::missingFactory() : function () {
            return 4;
        };
    }

    /** Braced closure without return type or use clause enters via closure_args '{'. */
    public function plainBracedClosure(): \Closure
    {
        return function () {
            return 5;
        };
    }

    /** Relative (namespace-qualified, not fully-qualified) name in body state. */
    public function relativeQualifiedNameInBody(): \Closure
    {
        return function (): void {
            Grouped\GroupInterface::noop();
        };
    }

    /** Object operator followed by a newline exercises ignore_next whitespace. */
    public function chainedAcrossLines(): \Closure
    {
        return function (): string {
            return $this
                ->describe();
        };
    }

    /** Newline directly after the object operator lands in ignore_next. */
    public function chainedWithOperatorEOL(): \Closure
    {
        return function (): string {
            return $this->
                describe();
        };
    }

    /** Braced-string accessor after an object operator hits ignore_next default. */
    public function braceAccessorAfterOperator(): \Closure
    {
        return function (): void {
            $bag = ['k' => 1];
            $bag->{'k'} = 2;
        };
    }

    /** Instantiating through a variable exercises id_start's T_VARIABLE arm. */
    public function newVariableClass(): \Closure
    {
        return function (): object {
            $cls = Suit::class;

            return new $cls();
        };
    }

    /** Named-argument colon flows through id_name back into the body state. */
    public function namedArgumentsCall(): \Closure
    {
        return function (): int {
            return strlen(string: 'abc');
        };
    }

    /** Anonymous class with relative string parent and qualified interface. */
    public function anonymousRelativeAncestry(): \Closure
    {
        return function (): array {
            $first = new class extends Suit {};
            $second = new class implements Grouped\GroupInterface {};

            return [$first, $second];
        };
    }
}
