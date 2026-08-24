<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture exercising the named-function branch of the reflection scanner.
 * PHPStan does not analyse inner named functions (see phpstan#165), so the
 * construct lives in the excluded fixtures directory on purpose.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures\Rich;

use Closure;

final class NamedFunctionProbe
{
    public static int $counter = 0;

    /**
     * One-line body mixing a modifier reset, an inner named function and a
     * short arrow return: each token drives a different scanner state.
     */
    public function tricky(): Closure
    {
        static $memo = 0;

        return function () use ($memo): callable {
            if ($memo < 0) {
                function ghost(): void
                {
                }
            }

            return fn (): int => 5;
        };
    }
}
