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
 * Fresh-file probe: the first reflection accessor touched on this file must
 * be getFunctions(), exercising its lazy fetchItems() initialization.
 */
final class LazyFunctionsProbe
{
    /**
     * Returns a closure calling a namespaced function without importing it,
     * so resolution falls through to function_exists().
     */
    public function callsNamespacedFunction(): \Closure
    {
        return static fn (): string => tokenizerNamedFunction();
    }
}
