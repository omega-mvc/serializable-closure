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

trait Colorable
{
    public string $color = 'red';
}

enum Flavor: string
{
    case Sweet = 'sweet';
}

const RICH_MARKER = 'rich-constant';

class RichHost implements Wearable
{
    use Colorable;

    public function wear(): void
    {
    }

    /** Array holding closures: exercises the array branch of the walker. */
    public array $toolbox = [];

    /** Unstructured holder for the stdClass branch of the walker. */
    public ?stdClass $box = null;

    /**
     * Prepares complex properties before returning a bound closure.
     */
    public function boundWithProps(): Closure
    {
        $this->toolbox = [fn (): int => 5];
        $this->box = new stdClass();
        $this->box->cb = fn (): int => 6;

        return function (): array {
            return [
                $this->color,
                self::class,
                ($this->toolbox[0])(),
                ($this->box->cb)(),
            ];
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
        function (#[\SensitiveParameter] string $prefix = EOL, ?DT $when = null) use ($local, $intSize): string {
            /** docblock inside the body */
            // plain comment inside the body
            #trackme
            $len = count_all(array_keys((array) $local)) + strlen($prefix) + $intSize;
            $date = $when ?? new DT();
            $kind = $this->color ?? '';
            $copy = new ArrayObject([$date]);
            $isWearable = $this instanceof Wearable;
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
            $interp = "kind=${kind}!";
            $defined = RICH_MARKER;

            return $prefix . '|' . $len . '|' . ($isWearable ? 'y' : 'n')
                . '|' . get_class($copy)
                . '|' . $anon->hi()
                . '|' . implode(',', $aliased)
                . '|' . $nestedFn(1)
                . '|' . $innerArrow('a')
                . '|' . $interp
                . '|' . var_export($defined, true)
                . '|' . var_export($kind, true)
                . '|' . (string) count($date ? [1] : [])
                . '|' . var_export(static::class, true)
                . '|' . (string) $line;
        };
    }
}
