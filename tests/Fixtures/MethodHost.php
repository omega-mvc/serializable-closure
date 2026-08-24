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

/**
 * Host of bound closures exercising $this/self/parent reflection paths.
 */
class MethodHost
{
    /** Constant used by self:: resolution tests. */
    public const ANSWER = 42;

    /** Closure reading instance state: requires binding. */
    public function boundThis(): \Closure
    {
        return function (): int {
            return $this->answer();
        };
    }

    /** Closure resolving self::CONSTANT: requires scope. */
    public function selfConstant(): \Closure
    {
        return function (): int {
            return self::ANSWER;
        };
    }

    /** Closure calling an instance method through $this. */
    public function answer(): int
    {
        return self::ANSWER;
    }

    /** Plain static closure without scope nor binding. */
    public function plainStatic(): \Closure
    {
        return static fn (): int => 1;
    }
}

/**
 * Child fixture exercising parent:: references.
 */
class MethodHostChild extends MethodHost
{
    /** Closure resolving parent::CONSTANT: requires scope. */
    public function parentConstant(): \Closure
    {
        return function (): int {
            return parent::ANSWER;
        };
    }
}
