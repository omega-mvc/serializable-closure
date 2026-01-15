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

/**
 * Self reference class.
 *
 * The `SelfReference` class providing functionality for creating a self-reference instance.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Support
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
class SelfReference
{
    /**
     * The unique hash representing the object.
     *
     * @var string Holds the unique hash representing the object.
     */
    public string $hash;

    /**
     * Creates a new self reference instance.
     *
     * @param string $hash Holds the unique hash representing the object.
     * @return void
     */
    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }
}
