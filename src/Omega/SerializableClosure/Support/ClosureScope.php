<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.3
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

namespace Omega\SerializableClosure\Support;

use SplObjectStorage;

/**
 * Closure scope class.
 *
 * The `ClosureScope` class manages the serialization scope for closures,
 * keeping track of the number of serializations and closures that need to
 * be serialized within the current scope.
 * *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Support
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
final class ClosureScope extends SplObjectStorage
{
    /**
     * The number of serializations in current scope.
     *
     * @var int Holds the number of serializations in current scope.
     */
    public int $serializations = 0;

    /**
     * The number of closures that have to be serialized.
     *
     * @var int Holds the number of closure that have to be serialized.
     */
    public int $toSerialize = 0;
}
