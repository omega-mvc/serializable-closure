<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures;

use \ArrayObject;
use Tests\Fixtures\{Suit as S2, Wearable, Grouped\GroupInterface as GI3};
use function \count as cnt2;
use function Tests\Fixtures\{tokenizerArraySort as tas};
use const \PHP_EOL as EOL2;
use const ProbeConsts\{PROBE_CONST as PC2};

/**
 * Fresh-file probe for the fetchItems() scanner itself: leading-backslash
 * imports, grouped use declarations and file-level new/invoke states.
 */
$scanLateCall = (new ArrayObject([1]))->
    count();

$scanAnonymous = new class {
    public int $mark = 1;
};

class FetchScanProbe
{
    /**
     * Anchor closure reflected by the suite to trigger the file scan.
     */
    public function probe(): \Closure
    {
        return static fn (): int => 1;
    }
}
