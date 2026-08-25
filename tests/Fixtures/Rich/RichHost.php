<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture file intentionally dense of constructs: it feeds every branch of
 * ReflectionClosure::fetchItems() and the code-extraction state machine.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures\Rich;

use ArrayObject;
use Closure;
use stdClass;
use Tests\Fixtures\Colorable;
use DateTimeImmutable as DT;

use function array_keys as ak;
use function count as count_all;
use function strlen;

use const PHP_EOL as EOL;
use const PHP_INT_SIZE;

interface Wearable
{
    public function wear(): void;
}

trait SinkTrait
{
    public string $sinkColor = 'deep';
}

enum Flavor: string
{
    case Sweet = 'sweet';
}

const RICH_MARKER = 'rich-constant';

class RichHost implements Wearable
{
    use Colorable;
    use SinkTrait;

    public function wear(): void
    {
    }

    /**
     * Array holding closures: exercises the array branch of the walker.
     *
     * @var list<\Closure>
     */
    public array $toolbox = [];

    /** Unstructured holder for the stdClass branch of the walker. */
    public ?stdClass $box = null;

    /** Internal (non-user-defined) object: attached as-is by the walker. */
    public ?ArrayObject $internal = null;

    /** First alias slot: same object exposed twice hits the storage cache. */
    public ?\Tests\Fixtures\UserDefinedFixture $aliasOne = null;

    /** Second alias slot sharing the very same instance. */
    public ?\Tests\Fixtures\UserDefinedFixture $aliasTwo = null;

    /**
     * Self-referencing array exercising the recursion sentinel.
     *
     * @var array<string, mixed>
     */
    public array $loop = [];

    /**
     * Prepares complex properties before returning a bound closure.
     */
    public function boundWithProps(): Closure
    {
        $cbA = fn (): int => 5;
        $cbB = fn (): int => 6;
        $box = new stdClass();
        $box->cb = $cbB;
        $shared = new \Tests\Fixtures\UserDefinedFixture();

        $this->toolbox = [$cbA];
        $this->box = $box;
        $this->internal = new ArrayObject(['probe']);
        $this->aliasOne = $shared;
        $this->aliasTwo = $shared;

        return function () use ($cbA, $cbB, $box): array {
            return [
                $this->color,
                self::class,
                $cbA(),
                $cbB(),
                count((array) $box),
            ];
        };
    }

    /** Closure resolving __CLASS__ inside a real class scope. */
    public function classConstant(): \Closure
    {
        return function (): string {
            return __CLASS__;
        };
    }

    /**
     * Returns the kitchen-sink closure used by reflection tests.
     */
    public function sink(): Closure
    {
        $local = new ArrayObject([]);
        $intSize = PHP_INT_SIZE;

        return
        function (
            #[\SensitiveParameter] string $prefix = EOL,
            ?DT $when = null,
        ) use (
            $local,
            $intSize
): string {
            /** docblock inside the body */
            // plain comment inside the body
            #trackme
            $len = count_all(array_keys((array) $local)) + strlen($prefix) + $intSize;
            $date = $when;
            $kind = $this->color;
            $copy = new ArrayObject([$date]);
            $isWearable = $when instanceof self;
            $line = __LINE__;
            $anon = new class {
                public function hi(): string
                {
                    return 'hi';
                }
            };
            $aliased = ak((array) $copy);
            $nestedFn = function (int $n) use ($local): int {
                return $n + count_all((array) $local);
            };
            $innerArrow = static fn (string $s): string => strtoupper($s);
            $interp = "kind={$kind}!";
            $defined = RICH_MARKER;

            return $prefix . '|' . $len . '|' . ($isWearable ? 'Y' : 'N')
                . '|' . get_class($copy)
                . '|' . $anon->hi()
                . '|' . implode(',', $aliased)
                . '|' . $nestedFn(1)
                . '|' . $innerArrow('a')
                . '|' . $interp
                . '|' . var_export($defined, true)
                . '|' . var_export($kind, true)
                . '|' . (string) count($when !== null ? [1] : [])
                . '|' . (string) $line;
        };
    }
}
