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

use Omega\SerializableClosure\Support\ClosureStream;

function closureStreamUrl(string $code): string
{
    return ClosureStream::STREAM_PROTO . '://' . $code;
}

test('the stream wrapper registers under its custom protocol', function () {
    ClosureStream::register();

    expect(in_array(ClosureStream::STREAM_PROTO, stream_get_wrappers()))->toBeTrue();
});

test('registration is idempotent', function () {
    ClosureStream::register();
    ClosureStream::register();

    expect(in_array(ClosureStream::STREAM_PROTO, stream_get_wrappers()))->toBeTrue();
});

test('reading the stream yields php code returning the closure source', function () {
    $handle = fopen(closureStreamUrl('fn (): int => 1'), 'rb');

    expect($handle)->toBeResource();

    $contents = (string) stream_get_contents($handle);
    fclose($handle);

    expect($contents)->toBe("<?php\nreturn fn (): int => 1;");
});

test('eof is reached after the whole payload has been read', function () {
    $handle = fopen(closureStreamUrl('42;'), 'rb');

    expect(feof($handle))->toBeFalse();

    stream_get_contents($handle);

    expect(feof($handle))->toBeTrue();

    fclose($handle);
});

test('stream_stat exposes the payload length', function () {
    $handle = fopen(closureStreamUrl('return 7;'), 'rb');

    $expected = strlen("<?php\nreturn return 7;;");

    expect(fstat($handle)['size'])->toBe($expected);

    fclose($handle);
});

test('url_stat answers stat() calls with a zero length placeholder', function () {
    // url_stat runs on a fresh wrapper instance whose payload was never opened,
    // so the reported size is the null-coalesced default.
    $stats = stat(closureStreamUrl('42;'));

    expect($stats['size'])->toBe(0)
        ->and($stats[7])->toBe(0);
});

test('set_option is not supported and reports false', function () {
    $handle = fopen(closureStreamUrl('42;'), 'rb');

    expect(@stream_set_blocking($handle, false))->toBeFalse();

    fclose($handle);
});

test('seeking within bounds moves the read pointer', function () {
    $handle = fopen(closureStreamUrl('1234567890'), 'rb');
    fread($handle, 7); // "<?php\nr"

    expect(ftell($handle))->toBe(7)
        ->and(fseek($handle, 2))->toBe(0)
        ->and(ftell($handle))->toBe(2)
        ->and(fread($handle, 4))->toBe("php\n")
        ->and(fseek($handle, -3, SEEK_CUR))->toBe(0)
        ->and(fseek($handle, strlen("<?php\n") + 10, SEEK_END))->toBe(-1);

    fclose($handle);
});

test('stream_seek resolves relative whences the engine never forwards', function () {
    // fseek() forwards only absolute seeks to userland wrappers; SEEK_CUR and
    // SEEK_END branches are exercised directly on the wrapper instance.
    $reflection = new ReflectionClass(ClosureStream::class);
    $instance = $reflection->newInstanceWithoutConstructor();

    $length = new ReflectionProperty(ClosureStream::class, 'length');
    $length->setValue($instance, 10);

    $pointer = new ReflectionProperty(ClosureStream::class, 'pointer');

    expect($instance->stream_seek(2, SEEK_CUR))->toBeTrue()
        ->and($pointer->getValue($instance))->toBe(2)
        ->and($instance->stream_seek(9, SEEK_CUR))->toBeFalse()
        ->and($pointer->getValue($instance))->toBe(2)
        ->and($instance->stream_seek(-4, SEEK_END))->toBeTrue()
        ->and($pointer->getValue($instance))->toBe(6)
        ->and($instance->stream_seek(3, SEEK_END))->toBeFalse()
        ->and($pointer->getValue($instance))->toBe(6);
});
