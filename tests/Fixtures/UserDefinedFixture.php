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

use Tests\Fixtures\ParentFixture;

/**
 * Fixture exercising every property-walking branch of Native.
 */
class UserDefinedFixture extends ParentFixture
{
    /** Scalar public property. */
    public string $label = 'fixture';

    /** Object property holding a closure, wrapped during serialization. */
    public ?\Closure $callback = null;

    /** Static properties are skipped by the walker. */
    public static string $shared = 'static';

    /** Readonly properties are skipped by the pointer mapper. */
    public readonly string $frozen;

    /** Uninitialized typed property, skipped by the walker. */
    public string $later;

    /** Constructor initializes the readonly property only. */
    public function __construct()
    {
        $this->frozen = 'immutable';
    }
}
