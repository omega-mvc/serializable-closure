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
 * Holder with untyped properties so the serializer can store wrappers in them.
 */
class UntypedHolder
{
    /** Holds anything, including serializer wrappers. */
    public mixed $payload;

    /** Second slot used by deferred-binding assertions. */
    public mixed $spare;
}
