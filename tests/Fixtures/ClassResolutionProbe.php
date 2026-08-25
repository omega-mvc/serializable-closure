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
 * Fresh-file probe: class-name resolution branches of the getCode() walker
 * (lazy classes cache, namespace prefixing, keyword handling).
 */
final class ClassResolutionProbe
{
    /**
     * Returns a closure whose body exercises every class-resolution arm.
     *
     * The body is never executed: it only has to tokenize.
     */
    public function resolvesImportedClasses(): \Closure
    {
        return static function (): object {
            $named = new SuitAlias;
            $made  = new SuitAlias();
            $me    = new self();
            $late  = new static;
            $stamp = new DateTimeImmutable();
            $ref   = SuitAlias::class;
            $what  = SOME_UNDEFINED_PROBE;

            return $made;
        };
    }
}
