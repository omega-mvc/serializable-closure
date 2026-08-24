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

use Closure;
use Omega\SerializableClosure\Support\ReflectionClosure;

/**
 * Test double exposing the protected reflection-cache accessors.
 */
final class ExposedReflectionClosure extends ReflectionClosure
{
    /** Creates the reflection for the given closure. */
    public function __construct(Closure $closure)
    {
        parent::__construct($closure);
    }

    /**
     * Exposes getClasses().
     *
     * @return array<string, string> Return the imported classes.
     */
    public function classes(): array
    {
        return $this->getClasses();
    }

    /**
     * Exposes getFunctions().
     *
     * @return array<string, string> Return the imported functions.
     */
    public function functions(): array
    {
        return $this->getFunctions();
    }

    /**
     * Exposes getConstants().
     *
     * @return array<string, string> Return the imported constants.
     */
    public function constants(): array
    {
        return $this->getConstants();
    }

    /**
     * Exposes getStructures().
     *
     * @return list<array{type: string, name: string, start: int, end: int}> Return the scanned structures.
     */
    public function structures(): array
    {
        return $this->getStructures();
    }
}
