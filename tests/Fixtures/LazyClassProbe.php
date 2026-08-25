<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures;

use Tests\Fixtures\Suit as SuitAlias;

/**
 * Fresh-file probe: the FIRST class resolution on this file happens through
 * the parenthesized-new arm of the getCode() walker, exercising the lazy
 * getClasses() initialization on that specific path.
 */
final class LazyClassProbe
{
    /**
     * Returns a closure instantiating an imported class with parentheses.
     */
    public function instantiatesImportedClass(): \Closure
    {
        return static function (): object {
            $made = new SuitAlias();

            return $made;
        };
    }
}
