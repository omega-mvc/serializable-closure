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

use Closure;
use Exception;
use Omega\SerializableClosure\Serializers\Native;
use Omega\SerializableClosure\Support\ReflectionClosure;
use Tests\Fixtures\ExposedReflectionClosure;
use ReflectionException as NativeReflectionException;
use Tests\Fixtures\AttributeHost;
use Tests\Fixtures\MethodHost;
use Tests\Fixtures\MethodHostChild;
use Tests\Fixtures\Rich\RichHost;

function reflect(Closure $closure): ReflectionClosure
{
    return new ReflectionClosure($closure);
}

test('it detects plain closures', function () {
    $rc = reflect(function (): int {
        return 1;
    });

    expect($rc->isStatic())->toBeFalse()
        ->and($rc->isShortClosure())->toBeFalse();
});

test('it detects static closures', function () {
    $rc = reflect(static function (): int {
        return 2;
    });

    expect($rc->isStatic())->toBeTrue()
        ->and($rc->isShortClosure())->toBeFalse();
});

test('it detects arrow functions', function () {
    $rc = reflect(fn (): int => 3);

    expect($rc->isStatic())->toBeFalse()
        ->and($rc->isShortClosure())->toBeTrue();
});

test('it detects static arrow functions', function () {
    $rc = reflect(static fn (): int => 4);

    expect($rc->isStatic())->toBeTrue()
        ->and($rc->isShortClosure())->toBeTrue();
});

test('a bound closure requires binding but not scope', function () {
    $rc = reflect((new MethodHost())->boundThis());

    expect($rc->isBindingRequired())->toBeTrue()
        ->and($rc->isScopeRequired())->toBeFalse();
});

test('self:: references require the scope class', function () {
    $rc = reflect((new MethodHost())->selfConstant());

    expect($rc->isScopeRequired())->toBeTrue()
        ->and($rc->isBindingRequired())->toBeFalse();
});

test('parent:: references require the scope class too', function () {
    $rc = reflect((new MethodHostChild())->parentConstant());

    expect($rc->isScopeRequired())->toBeTrue();
});

test('getCode extracts a classic closure body', function () {
    $code = reflect(function (int $x): int {
        return $x + 1;
    })->getCode();

    expect($code)->toContain('function (int $x): int')
        ->and($code)->toContain('return $x + 1;');
});

test('getCode keeps the static keyword for static closures', function () {
    expect(reflect(static function (): void {
    })->getCode())
        ->toStartWith('static function');
});

test('getCode keeps the fn keyword for arrow functions', function () {
    expect(reflect(fn (): string => 'arrow')->getCode())
        ->toStartWith('fn (');
});

test('getCode replaces __FILE__ and __DIR__ with literals', function () {
    $code = reflect(function (): array {
        return [__FILE__, __DIR__];
    })->getCode();

    expect($code)->not->toContain('__FILE__')
        ->and($code)->not->toContain('__DIR__')
        ->and($code)->toContain('.php');
});

test('getCode resolves the closure magic constants to {closure} exports', function () {
    $code = reflect(function (): array {
        return [__FUNCTION__, __METHOD__];
    })->getCode();

    expect($code)->toContain('{closure}')
        ->and($code)->not->toContain('__FUNCTION__')
        ->and($code)->not->toContain('__METHOD__');
});

test('getCode resolves __CLASS__ inside a real class scope', function () {
    $code = reflect((new RichHost())->classConstant())->getCode();

    expect($code)->not->toContain('__CLASS__');
});

test('getCode resolves __NAMESPACE__ with the real namespace literal', function () {
    $code = reflect(function (): string {
        return __NAMESPACE__;
    })->getCode();

    // Pest prefixes compiled test namespaces with 'P\'; var_export doubles
    // the backslashes, so compare against its own escaping.
    $namespaceLiteral = substr(var_export('Tests\Unit\Support', true), 1, -1);

    expect($code)->toContain($namespaceLiteral)
        ->and($code)->not->toContain('__NAMESPACE__');
});

test('getCode resolves __TRAIT__ inside trait-backed closures', function () {
    $code = reflect((new RichHost())->traitConstant())->getCode();

    expect($code)->not->toContain('__TRAIT__')
        ->and($code)->toContain('Colorable');
});

test('first-class callable methods extract only the signature and body', function () {
    $code = reflect((new MethodHost())->answer(...))->getCode();

    expect($code)->toStartWith('function')
        ->and($code)->toContain('ANSWER');
});

test('first-class callables of global functions have no source file', function () {
    $rc = reflect(strlen(...));

    expect($rc->getName())->toBe('strlen')
        ->and(fn () => $rc->getUseVariables())->toThrow(NativeReflectionException::class);
});

test('attributes are rendered into the extracted code', function () {
    $code = reflect((new AttributeHost())->scalarArgs())->getCode();

    expect($code)->toStartWith('#[')
        ->and($code)->toContain('SensitiveParameter')
        ->and($code)->toContain('Deprecated');
});

test('non-scalar attribute arguments abort code extraction', function () {
    $rc = reflect((new AttributeHost())->nonScalarArgs());

    expect(fn () => $rc->getCode())->toThrow(NativeReflectionException::class, 'non-scalar');
});

test('the constructor accepts and caches pre-extracted code', function () {
    $rc = new ReflectionClosure(fn (): int => 5, 'fn (): int => 5');

    expect($rc->getCode())->toBe('fn (): int => 5')
        ->and($rc->getUseVariables())->toBe([]);
});

test('eval-created closures cannot be tokenized from disk', function () {
    $closure = eval('return fn (): int => 9;');

    if (! $closure instanceof Closure) {
        throw new Exception('Eval did not produce a closure.');
    }

    expect(fn () => reflect($closure)->getCode())
        ->toThrow(NativeReflectionException::class, 'Cannot read');
});

test('use variables are extracted by name for classic closures', function () {
    $alpha = 1;
    $beta = 'two';
    $unused = 3.0;

    $uses = reflect(fn () => [$alpha, $beta, $unused])->getUseVariables();

    expect($uses)->toBe(['alpha' => 1, 'beta' => 'two', 'unused' => 3.0]);
});

test('short closures report their captured values via static variables', function () {
    $value = 42;

    expect(reflect(fn (): int => $value + 0)->getUseVariables())->toBe(['value' => 42]);
});

test('the rich fixture file exposes imported classes, functions and constants', function () {
    $host = new RichHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();
    $functions = $rc->functions();
    $constants = $rc->constants();
    $structures = $rc->structures();

    expect($classes)->toHaveKey('arrayobject')
        ->and($classes)->toHaveKey('dt')
        ->and($functions)->toHaveKeys(['ak', 'count_all'])
        ->and($functions['strlen'])->toBe('\\strlen')
        ->and($constants)->toHaveKey('EOL')
        ->and($constants['EOL'])->toBe('\\PHP_EOL');

    $types = array_column($structures, 'type');
    expect($types)->toContain('interface')
        ->and($types)->toContain('trait')
        ->and($types)->toContain('class')
        ->and($types)->toContain('enum')
        ->and(count($structures))->toBe(4);
});

test('the kitchen-sink closure keeps working after a round trip', function () {
    $host = new RichHost();

    $restored = unserialize(serialize(new \Omega\SerializableClosure\SerializableClosure(
        $host->sink()
    )));

    assert($restored instanceof \Omega\SerializableClosure\SerializableClosure);
    expect($restored())->toContain('|N|');
});

final class TrickyHost
{
    public static int $counter = 0;

    public function tricky(): Closure
    {
        static $memo = 0;

        return function () use ($memo): callable {
            if ($memo < 0) {
                $ghost = function (): void {
                };
            }

            return fn (): int => 5;
        };
    }
}

test('one-line methods exercise the modifier, named-function and nesting paths', function () {
    $rc = reflect((new TrickyHost())->tricky());

    expect($rc->getCode())->toContain('function')
        ->and($rc->getCode())->not->toContain('tricky');
});

test('isShortClosure memoizes independently from isStatic', function () {
    $rc = reflect(static fn (): int => 6);

    expect($rc->isShortClosure())->toBeTrue()
        ->and($rc->isStatic())->toBeTrue();
});

test('multiline arrows cross comma and bracket boundaries', function () {
    $rc = reflect(fn (): array => [
        1,
        2,
    ]);

    expect($rc->getCode())->toContain('=>')
        ->and($rc->getCode())->toContain('2');
});

test('the trackme comment injects the provenance header', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();

    $code = reflect($host->sink())->getCode();

    expect($code)->toContain('Date      : ')
        ->and($code)->toContain('Timestamp : ');
});
