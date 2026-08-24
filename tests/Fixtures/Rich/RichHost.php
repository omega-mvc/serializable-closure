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

class RichHost implements Wearable
{
    use Colorable;

    public function wear(): void
    {
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
            $len = count_all(array_keys((array) $local)) + strlen($prefix) + $intSize;
            $date = $when ?? new DT();
            $kind = $this->color ?? '';
            $copy = new ArrayObject([$date]);
            $isWearable = $this instanceof Wearable;

            return $prefix . '|' . $len . '|' . ($isWearable ? 'y' : 'n') . '|' . get_class($copy);
        };
    }
}
