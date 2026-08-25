<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Fresh-file probe: __TRAIT__ inside a closure forces the structures cache
 * to initialize lazily before any other accessor touches this file.
 */
trait TraitProbe
{
    /**
     * Returns a closure reporting the trait it was defined in.
     */
    public function traitConstClosure(): \Closure
    {
        return static function (): string {
            return __TRAIT__;
        };
    }
}
