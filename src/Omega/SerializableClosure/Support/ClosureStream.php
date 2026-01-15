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

use AllowDynamicProperties;

use function substr;
use function stat;
use function strlen;

#[AllowDynamicProperties]
/**
 * Closure stream class.
 *
 * The `ClosureStem` class manages a stream for serializable closures,
 * registering a custom stream.
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
class ClosureStream
{
    /**
     * The stream protocol.
     *
     * @var string STREAM_PROTO Holds the stream protocol.
     */
    public const STREAM_PROTO = 'omega-serializable-closure';

    /**
     * Checks if this stream is registered.
     *
     * @var bool Holds the flag for check if this stream is registered.
     */
    protected static bool $isRegistered = false;

    /**
     * The stream content.
     *
     * @var string Holds the stream content.
     */
    protected string $content;

    /**
     * The stream content length.
     *
     * @var int|null Holds the stream content length.
     */
    protected ?int $length = null;

    /**
     * The stream pointer.
     *
     * @var int Holds the stream pointer.
     */
    protected $pointer = 0;

    /**
     * Opens a file or URL.
     *
     * @param string      $path       Holds the path to the file or URL.
     * @param string      $mode       Holds the mode used to open the file or URL.
     * @param string|int  $options    Holds additional flags set by the streams API.
     * @param string|null $openedPath If passed, should be set to the full path of the file/resource
     * @return bool Return true if open stream, false if not.
     */
    public function stream_open(string $path, string $mode, string|int $options, ?string &$openedPath): bool
    {
        $this->content = "<?php\nreturn " . substr($path, strlen(static::STREAM_PROTO . '://')) . ';';
        $this->length  = strlen($this->content);

        return true;
    }

    /**
     * Reads from the stream.
     *
     * @param int $count Holds the number of bytes to read from the stream.
     * @return string Returns the read string.
     */
    public function stream_read(int $count): string
    {
        $value = substr($this->content, $this->pointer, $count);

        $this->pointer += $count;

        return $value;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @return bool Return true if test passed, false if not.
     */
    public function stream_eof(): bool
    {
        return $this->pointer >= $this->length;
    }

    /**
     * Change stream options.
     *
     * @param int $option Holds the option to set.
     * @param int $arg1   Holds the first argument.
     * @param int $arg2   Holds the second argument.
     * @return bool Returns true on success or false on failure.
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    /**
     * Retrieve information about a file resource.
     *
     * @return array|bool Returns an array with information about the stream, or false on failure.
     */
    public function stream_stat(): array|bool
    {
        $stat    = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;

        return $stat;
    }

    /**
     * Retrieve information about a file.
     *
     * @param string $path  Holds the path to the file or URL.
     * @param int    $flags Holds additional flags set by the streams API.
     * @return array|bool Returns an array with information about the stream, or false on failure.
     */
    public function url_stat(string $path, int $flags): array|bool
    {
        $stat    = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;

        return $stat;
    }

    /**
     * Seeks to a specific location in a stream.
     *
     * @param int $offset Holds the stream offset.
     * @param int $whence Holds the reference position.
     * @return bool Returns true on success or false on failure.
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $crt = $this->pointer;

        switch ($whence) {
            case SEEK_SET:
                $this->pointer = $offset;

                break;
            case SEEK_CUR:
                $this->pointer += $offset;

                break;
            case SEEK_END:
                $this->pointer = $this->length + $offset;

                break;
        }

        if ($this->pointer < 0 || $this->pointer >= $this->length) {
            $this->pointer = $crt;

            return false;
        }

        return true;
    }

    /**
     * Retrieve the current position of a stream.
     *
     * @return int Returns the current position of the stream.
     */
    public function stream_tell(): int
    {
        return $this->pointer;
    }

    /**
     * Registers the stream.
     *
     * @return void
     */
    public static function register(): void
    {
        if (! static::$isRegistered) {
            static::$isRegistered = stream_wrapper_register(static::STREAM_PROTO, __CLASS__);
        }
    }
}
