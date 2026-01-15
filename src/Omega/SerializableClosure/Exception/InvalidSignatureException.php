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

namespace Omega\SerializableClosure\Exception;

use Exception;

/**
 * Invalid signature exception class.
 *
 * THe `InvalidSignaturesException` class thrown when the signature of a serialized closure
 * is invalid or modified.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Exception
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
class InvalidSignatureException extends Exception
{
    /**
     * InvalidSignatureException constructor.
     *
     * @param string $message Holds the exception message to throw.
     * @return void
     */
    public function __construct(
        string $message = 'Your serialized closure might have been modified or it\'s unsafe to be unserialize.'
    ) {
        parent::__construct($message);
    }
}
