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
 * Host producing closures decorated with attributes for code-extraction tests.
 */
class AttributeHost
{
    /** Closure with a bare attribute and one carrying a named argument. */
    public function scalarArgs(): \Closure
    {
        return #[\Deprecated(message: 'use something else')]
        function (#[\SensitiveParameter] string $secret): string {
            return $secret;
        };
    }

    /** Closure whose attribute carries a non-scalar argument. */
    public function nonScalarArgs(): \Closure
    {
        return #[MissingAttributeClass(['map' => [1, 2]])]
        function (): int {
            return 0;
        };
    }
}
