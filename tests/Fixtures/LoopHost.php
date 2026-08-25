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
 * Host fixture whose array property can be wired into a self reference,
 * exercising the recursion-marker guards of the Native serializer.
 */
class LoopHost
{
    /** Array slot that tests wire into a self-referential cycle. */
    public array $loop = [];

    /**
     * Returns an automatically bound closure reading the loop property.
     */
    public function reader(): \Closure
    {
        return function (): array {
            return $this->loop;
        };
    }
}
