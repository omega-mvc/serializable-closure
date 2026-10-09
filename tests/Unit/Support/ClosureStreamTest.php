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

namespace Tests\Unit\Support;

use Exception;
use Omega\SerializableClosure\Support\ClosureStream;
use Tests\TestCase;

final class ClosureStreamTest extends TestCase
{
    private static function closureStreamUrl(string $code): string
    {
        return ClosureStream::STREAM_PROTO . '://' . $code;
    }

    /**
     * Opens a closure stream handle, failing loudly when unavailable.
     *
     * @return resource Return the stream handle.
     */
    private static function openStream(string $code): mixed
    {
        $handle = fopen(self::closureStreamUrl($code), 'rb');

        if (! is_resource($handle)) {
            throw new Exception('Cannot open the closure stream.');
        }

        return $handle;
    }

    public function testTheStreamWrapperRegistersUnderItsCustomProtocol(): void
    {
        ClosureStream::register();

        $this->assertContains(ClosureStream::STREAM_PROTO, stream_get_wrappers());
    }

    public function testRegistrationIsIdempotent(): void
    {
        ClosureStream::register();
        ClosureStream::register();

        $this->assertContains(ClosureStream::STREAM_PROTO, stream_get_wrappers());
    }

    public function testReadingTheStreamYieldsPhpCodeReturningTheClosureSource(): void
    {
        $handle = self::openStream('fn (): int => 1');

        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        $this->assertSame("<?php\nreturn fn (): int => 1;", $contents);
    }

    public function testEofIsReachedAfterTheWholePayloadHasBeenRead(): void
    {
        $handle = self::openStream('42;');

        $this->assertFalse(feof($handle));

        stream_get_contents($handle);

        $this->assertTrue(feof($handle));

        fclose($handle);
    }

    public function testStreamStatExposesThePayloadLength(): void
    {
        $handle = self::openStream('return 7;');
        $stats = fstat($handle);

        if (! is_array($stats)) {
            throw new Exception('Cannot stat the closure stream.');
        }

        $this->assertSame(strlen("<?php\nreturn return 7;;"), $stats['size']);

        fclose($handle);
    }

    public function testUrlStatAnswersStatCallsWithAZeroLengthPlaceholder(): void
    {
        // url_stat runs on a fresh wrapper instance whose payload was never opened,
        // so the reported size is the null-coalesced default.
        $stats = stat(self::closureStreamUrl('42;'));

        if (! is_array($stats)) {
            throw new Exception('Cannot stat the closure stream url.');
        }

        $this->assertSame(0, $stats['size']);
        $this->assertSame(0, $stats[7]);
    }

    public function testSetOptionIsNotSupportedAndReportsFalse(): void
    {
        $handle = self::openStream('42;');

        $this->assertFalse(stream_set_blocking($handle, false));

        fclose($handle);
    }

    public function testSeekingWithinBoundsMovesTheReadPointer(): void
    {
        $handle = self::openStream('1234567890');
        fread($handle, 7); // "<?php\nr"

        $this->assertSame(7, ftell($handle));
        $this->assertSame(0, fseek($handle, 2));
        $this->assertSame(2, ftell($handle));
        $this->assertSame("php\n", fread($handle, 4));
        $this->assertSame(0, fseek($handle, -3, SEEK_CUR));
        $this->assertSame(-1, fseek($handle, strlen("<?php\n") + 10, SEEK_END));

        fclose($handle);
    }

    public function testStreamSeekResolvesRelativeWhencesTheEngineNeverForwards(): void
    {
        // fseek() forwards only absolute seeks to userland wrappers; SEEK_CUR and
        // SEEK_END branches are exercised directly on the wrapper instance.
        $reflection = new \ReflectionClass(ClosureStream::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $length = new \ReflectionProperty(ClosureStream::class, 'length');
        $length->setValue($instance, 10);

        $pointer = new \ReflectionProperty(ClosureStream::class, 'pointer');

        $this->assertTrue($instance->stream_seek(2, SEEK_CUR));
        $this->assertSame(2, $pointer->getValue($instance));
        $this->assertFalse($instance->stream_seek(9, SEEK_CUR));
        $this->assertSame(2, $pointer->getValue($instance));
        $this->assertTrue($instance->stream_seek(-4, SEEK_END));
        $this->assertSame(6, $pointer->getValue($instance));
        $this->assertFalse($instance->stream_seek(3, SEEK_END));
        $this->assertSame(6, $pointer->getValue($instance));
    }

    public function testStreamSeekLeavesThePointerAloneOnUnknownWhenceValues(): void
    {
        $reflection = new \ReflectionClass(ClosureStream::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $length = new \ReflectionProperty(ClosureStream::class, 'length');
        $length->setValue($instance, 10);

        $pointer = new \ReflectionProperty(ClosureStream::class, 'pointer');

        $this->assertTrue($instance->stream_seek(5, 42));
        $this->assertSame(0, $pointer->getValue($instance));
    }
}
