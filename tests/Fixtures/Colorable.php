<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

namespace Tests\Fixtures;

trait Colorable
{
    public string $color = 'red';

    /** Closure resolving __TRAIT__ inside the trait itself. */
    public function traitConstant(): \Closure
    {
        return function (): string {
            return __TRAIT__;
        };
    }
}
