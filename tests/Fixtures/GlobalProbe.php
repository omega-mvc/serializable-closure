<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

/**
 * Global-namespace probe: closures defined here carry an empty namespace
 * and no scope class, exercising the metadata fallbacks in getCode().
 */
class GlobalProbe
{
    /**
     * Returns a plain closure from the global namespace.
     */
    public function probe(): \Closure
    {
        return static function (): int {
            return 1;
        };
    }
}
