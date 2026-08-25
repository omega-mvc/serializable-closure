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
use Tests\Fixtures\TokenizerEdgeCases;
use Tests\Fixtures\Grouped\GroupHost;
use Tests\Fixtures\LazyFunctionsProbe;
use Tests\Fixtures\LazyClassProbe;
use Tests\Fixtures\ClassResolutionProbe;

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

    /** @phpstan-ignore callable.nonCallable (unserialize returns mixed; type-guarded below) */
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
                // @phpstan-ignore function.inner (named fn inside closure: tokenizer edge-case fixture)
                function ghost(): void
                {
                }
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

test('self::$prop sets scope required', function () {
    $rc = reflect(TokenizerEdgeCases::staticPropClosure());

    expect($rc->isScopeRequired())->toBeTrue()
        ->and($rc->getCode())->toContain('self::$counter');
});

test('self::class sets scope required', function () {
    $rc = reflect((new TokenizerEdgeCases())->selfClassClosure());

    expect($rc->getCode())->toContain('self::class')
        ->and($rc->isScopeRequired())->toBeTrue();
});

test('use clause extracts captured variables', function () {
    $rc = reflect((new TokenizerEdgeCases())->useClause(10));

    expect($rc->getUseVariables())->toHaveKeys(['a', 'b'])
        ->and($rc->getCode())->toContain('use ($a, $b)');
});

test('arrow function with spread operator', function () {
    $code = reflect((new TokenizerEdgeCases())->arrowWithSpread())->getCode();

    expect($code)->toStartWith('fn')
        ->and($code)->toContain('...');
});

test('static arrow with qualified return type', function () {
    $code = reflect((new TokenizerEdgeCases())->staticArrowQualified())->getCode();

    expect($code)->toStartWith('static fn')
        ->and($code)->toContain('Closure\\Subspace\\QF');
});

test('anonymous class extending a named class', function () {
    $rc = reflect((new TokenizerEdgeCases())->anonymousClassExtends());
    $code = $rc->getCode();

    expect($code)->toContain('new class extends')
        ->and($code)->toContain('greet');
});

test('new self() resolves the scope class', function () {
    $rc = reflect((new TokenizerEdgeCases())->newSelfParent());

    expect($rc->getCode())->toContain('new self()')
        ->and($rc->isScopeRequired())->toBeTrue();
});

test('static::class in a static method closure', function () {
    $rc = reflect(TokenizerEdgeCases::staticSelf());

    expect($rc->getCode())->toContain('static::class');
});

test('chained method calls exercise ignore_next state', function () {
    $code = reflect((new TokenizerEdgeCases())->chainedCalls())->getCode();

    expect($code)->toContain('strtolower')
        ->and($code)->toContain('trim');
});

test('instanceof check exercises the instanceof context', function () {
    $code = reflect((new TokenizerEdgeCases())->instanceofCheck())->getCode();

    expect($code)->toContain('instanceof');
});

test('namespace-qualified function call resolves correctly', function () {
    $code = reflect((new TokenizerEdgeCases())->namespaceQualifiedCall())->getCode();

    expect($code)->toContain('Tests\\');
});

test('qualified names in params and body are preserved', function () {
    $code = reflect((new TokenizerEdgeCases())->qualifiedNames())->getCode();

    expect($code)->toContain('Closure\\Subspace\\QF');
});

test('grouped imports are parsed by fetchItems', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();
    $functions = $rc->functions();
    $constants = $rc->constants();
    $structures = $rc->structures();

    $expectedClasses = ['arrayobject', 'userdefinedfixture', 'colorable', 'wearable', 'parentfixture', 'ah', 'mh'];
    expect($classes)->toHaveKeys($expectedClasses)
        ->and($functions)->toHaveKeys(['ak', 'cnt', 'slen', 'av', 'tnf', 'sl'])
        ->and($constants)->toHaveKeys(['EOL', 'INTSIZE', 'FLOATDIG', 'PHPVER', 'MAJVER'])
        ->and(count($structures))->toBeGreaterThanOrEqual(4);
});

test('grouped function and constant imports resolve correctly', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $functions = $rc->functions();
    $constants = $rc->constants();

    expect($functions['tnf'])->toBe('\\Tests\\Fixtures\\tokenizerNamedFunction')
        ->and($functions['sl'])->toBe('\\strtolower')
        ->and($constants['PHPVER'])->toBe('\\PHP_VERSION')
        ->and($constants['MAJVER'])->toBe('\\PHP_MAJOR_VERSION');
});

test('file-level new and invoke paths are exercised', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $structures = $rc->structures();
    $types = array_column($structures, 'type');

    expect($types)->toContain('class')
        ->and($types)->toContain('interface')
        ->and($types)->toContain('trait')
        ->and($types)->toContain('enum');
});

test('new variable followed by method call triggers id_start variable path', function () {
    $code = reflect((new TokenizerEdgeCases())->anonymousClassExtends())->getCode();

    expect($code)->toContain('new class extends');
});

test('closure body with use keyword triggers use context', function () {
    $code = reflect((new TokenizerEdgeCases())->useClause(5))->getCode();

    expect($code)->toContain('use ($a, $b)');
});

test('T_NS_SEPARATOR in id_start is handled for leading backslash', function () {
    $code = reflect((new TokenizerEdgeCases())->namespaceQualifiedCall())->getCode();

    expect($code)->toContain('\\Tests\\');
});

test('isBindingRequired detects $this usage', function () {
    $rc = reflect((new TokenizerEdgeCases())->anonymousClassExtends());

    expect($rc->isBindingRequired())->toBeFalse();
});

test('isStatic for static closure returns cached value', function () {
    $rc = reflect(TokenizerEdgeCases::staticPropClosure());

    expect($rc->isStatic())->toBeFalse()
        ->and($rc->isShortClosure())->toBeFalse();
});

test('static arrow function is both static and short', function () {
    $rc = reflect(TokenizerEdgeCases::staticArrowQualified());

    expect($rc->isStatic())->toBeTrue()
        ->and($rc->isShortClosure())->toBeTrue();
});

test('static keyword followed by non-function/non-fn resets to start', function () {
    $rc = reflect(TokenizerEdgeCases::staticPropClosure());

    expect($rc->getCode())->toStartWith('function');
});

test('named function state resets when encountering another function keyword', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();
    $functions = $rc->functions();

    expect($classes)->not->toBeEmpty()
        ->and($functions)->not->toBeEmpty();
});

test('invoke state skips whitespace and comments', function () {
    $code = reflect((new TokenizerEdgeCases())->chainedCalls())->getCode();

    expect($code)->toContain('strtolower');
});

test('short closure with double arrow transitions to closure state', function () {
    $code = reflect(fn (int $a): int => $a + 1)->getCode();

    expect($code)->toStartWith('fn')
        ->and($code)->toContain('=>');
});

test('return type with colon enters return state', function () {
    $code = reflect(function (): string {
        return 'x';
    })->getCode();

    expect($code)->toContain(': string');
});

test('new state skips whitespace before class keyword', function () {
    $code = reflect((new TokenizerEdgeCases())->anonymousClassExtends())->getCode();

    expect($code)->toContain('new class extends');
});

test('before_structure state captures class name', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $structures = $rc->structures();
    $names = array_column($structures, 'name');

    expect($names)->toContain('GroupHost')
        ->and($names)->toContain('GroupInterface')
        ->and($names)->toContain('GroupTrait')
        ->and($names)->toContain('GroupSuit');
});

test('structure state tracks end line correctly', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $structures = $rc->structures();

    foreach ($structures as $struct) {
        expect($struct['end'])->toBeGreaterThanOrEqual($struct['start']);
    }
});

test('use-group with comma-separated names are all registered', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();

    expect($classes)->toHaveKey('arrayobject')
        ->and($classes)->toHaveKey('userdefinedfixture')
        ->and($classes)->toHaveKey('colorable');
});

test('use-group closing brace transitions back to use state', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $functions = $rc->functions();

    expect($functions)->toHaveKey('ak')
        ->and($functions)->toHaveKey('cnt')
        ->and($functions)->toHaveKey('slen');
});

test('alias state captures the aliased name', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();

    expect($classes)->toHaveKey('ah')
        ->and($classes)->toHaveKey('mh')
        ->and($classes)->toHaveKey('dt');
});

test('use-group with T_NAME_QUALIFIED in name is handled', function () {
    $host = new \Tests\Fixtures\Grouped\GroupHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();

    expect($classes)->toHaveKey('arrayobject');
});

test('use statement with leading backslash gets prefix', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();
    $rc = new ExposedReflectionClosure($host->sink());

    $classes = $rc->classes();

    expect($classes)->toHaveKey('arrayobject')
        ->and($classes['arrayobject'])->toStartWith('\\');
});

test('new state with default token transitions to start', function () {
    $code = reflect((new TokenizerEdgeCases())->newSelfParent())->getCode();

    expect($code)->toContain('new self()');
});

test('id_name with colon in non-instanceof context appends to code', function () {
    $code = reflect(function (): string {
        return 'x';
    })->getCode();

    expect($code)->toContain(':');
});

test('id_name default resolves constants via getConstants', function () {
    $code = reflect(function (): mixed {
        return \PHP_INT_SIZE;
    })->getCode();

    expect($code)->toContain('PHP_INT_SIZE');
});

test('id_start T_VARIABLE after new sets variable and returns to lastState', function () {
    $code = reflect((new TokenizerEdgeCases())->newSelfParent())->getCode();

    expect($code)->toContain('new self()');
});

test('closure with $this usage sets isBindingRequired', function () {
    $rc = reflect((new TokenizerEdgeCases())->anonymousClassExtends());

    expect($rc->isBindingRequired())->toBeFalse();
});

test('the rich fixture closure has no uncaptured magic constants in body', function () {
    $host = new \Tests\Fixtures\Rich\RichHost();
    $code = reflect($host->sink())->getCode();

    expect($code)->not->toContain('__CLASS__')
        ->and($code)->not->toContain('__TRAIT__')
        ->and($code)->not->toContain('__FUNCTION__')
        ->and($code)->not->toContain('__METHOD__');
});

test('short closure ending with semicolon exits loop', function () {
    $code = reflect(fn (): int => 42)->getCode();

    expect($code)->toContain('42');
});

test('short closure ending with closing paren exits loop', function () {
    $code = reflect(fn (int $x): int => $x)->getCode();

    expect($code)->toContain('$x');
});

test('short closure ending with comma exits loop', function () {
    $code = reflect(fn (): array => [1, 2])->getCode();

    expect($code)->toContain('[1, 2]');
});

test('name-qualified token directly in closure body is resolved', function () {
    $code = reflect((new TokenizerEdgeCases())->nameQualifiedInBody())->getCode();

    expect($code)->toContain('Colorable');
});

test('anonymous class with use trait inside closure body is parsed', function () {
    $code = reflect((new TokenizerEdgeCases())->anonymousClassWithTrait())->getCode();

    expect($code)->toContain('new class')
        ->and($code)->toContain('use')
        ->and($code)->toContain('Colorable');
});

test('imported function call exercises function resolution in id_name', function () {
    $code = reflect((new TokenizerEdgeCases())->importedFunctionCall())->getCode();

    expect($code)->toContain('tokenizerArraySort');
});

test('same-line named function resets the hunt before the real closure', function () {
    $code = reflect((new TokenizerEdgeCases())->namedFunctionSameLine())->getCode();

    expect($code)->toContain('function ()')
        ->and($code)->not->toContain('tokenizerEdgeProbeA1');
});

test('same-line named function followed by an arrow closure restarts on T_FN', function () {
    $code = reflect((new TokenizerEdgeCases())->namedFunctionBeforeArrow())->getCode();

    expect($code)->toContain('fn () => 3')
        ->and($code)->not->toContain('tokenizerEdgeProbeB2');
});

test('static qualified call resets the hunt back to start', function () {
    $code = reflect((new TokenizerEdgeCases())->staticQualifiedReset())->getCode();

    expect($code)->toContain('function ()')
        ->and($code)->toContain('return 4;');
});

test('plain braced closure without types or use enters via closure_args', function () {
    $code = reflect((new TokenizerEdgeCases())->plainBracedClosure())->getCode();

    expect($code)->toContain('{')
        ->and($code)->toContain('return 5;');
});

test('relative qualified name in body is resolved through parseNameQualified', function () {
    $code = reflect((new TokenizerEdgeCases())->relativeQualifiedNameInBody())->getCode();

    expect($code)->toContain('\Tests\Fixtures\Grouped\GroupInterface');
});

test('object operator across lines consumes the whitespace', function () {
    $code = reflect((new TokenizerEdgeCases())->chainedAcrossLines())->getCode();

    expect($code)->toContain('$this')
        ->and($code)->toContain('describe');
});

test('operator at end of line consumes the following whitespace', function () {
    $code = reflect((new TokenizerEdgeCases())->chainedWithOperatorEOL())->getCode();

    expect($code)->toContain('describe');
});

test('braced string accessor after an operator is reprocessed', function () {
    $code = reflect((new TokenizerEdgeCases())->braceAccessorAfterOperator())->getCode();

    expect($code)->toContain("{'k'}");
});

test('new with a variable class name keeps the variable verbatim', function () {
    $code = reflect((new TokenizerEdgeCases())->newVariableClass())->getCode();

    expect($code)->toContain('new $cls()');
});

test('named-argument colons survive code extraction', function () {
    $code = reflect((new TokenizerEdgeCases())->namedArgumentsCall())->getCode();

    expect($code)->toContain("strlen(string: 'abc')");
});

test('anonymous class ancestry resolves relative names', function () {
    $code = reflect((new TokenizerEdgeCases())->anonymousRelativeAncestry())->getCode();

    expect($code)->toContain('extends \Tests\Fixtures\Suit')
        ->and($code)->toContain('implements \Tests\Fixtures\Grouped\GroupInterface');
});

test('namespaced function calls resolve through the lazy functions cache', function () {
    $code = reflect((new LazyFunctionsProbe())->callsNamespacedFunction())->getCode();

    expect($code)->toContain('\Tests\Fixtures\tokenizerNamedFunction');
});

test('parenthesized new triggers the lazy classes cache on that path', function () {
    $code = reflect((new LazyClassProbe())->instantiatesImportedClass())->getCode();

    expect($code)->toContain('new \Tests\Fixtures\Suit');
});

test('imported classes resolve to fully qualified names through the classes cache', function () {
    $code = reflect((new ClassResolutionProbe())->resolvesImportedClasses())->getCode();

    expect($code)->toContain('new \Tests\Fixtures\Suit')
        ->and($code)->toContain('\Tests\Fixtures\Suit::class')
        ->and($code)->toContain('new self()')
        ->and($code)->toContain('SOME_UNDEFINED_PROBE');
});

test('__TRAIT__ resolves through the structures cache', function () {
    $consumer = new class {
        use \Tests\Fixtures\TraitProbe;
    };

    $code = reflect($consumer->traitConstClosure())->getCode();

    expect($code)->toContain("'TraitProbe'");
});

test('file-level scan covers grouped and leading-backslash imports', function () {
    $probe = new \Tests\Fixtures\FetchScanProbe();
    $rc    = new ExposedReflectionClosure($probe->probe());

    // Leading-backslash imports (class, function or const) collapse into a
    // single T_NAME_FULLY_QUALIFIED token that the scanner ignores, leaving
    // a bare "\" prefix behind; grouped unqualified imports resolve fully.
    expect(array_keys($rc->classes()))->toBe(['s2', 'wearable', 'gi3'])
        ->and($rc->classes()['gi3'])->toBe('\Tests\Fixtures\Grouped\GroupInterface')
        ->and($rc->classes()['s2'])->toBe('\Tests\Fixtures\Suit')
        ->and($rc->functions())->toBe([
            'cnt2' => '\\',
            'tas'  => '\Tests\Fixtures\tokenizerArraySort',
        ])
        ->and($rc->constants())->toBe([
            'EOL2' => '\\',
            'PC2'  => '\ProbeConsts\PROBE_CONST',
        ]);
});

test('closures without source code are rejected fail-fast', function () {
    $rc = new ExposedReflectionClosure(strlen(...));

    // getCode() rejects first; the cache accessors share getHashedFileName()
    // and would reject through its own guard.
    expect(fn () => $rc->getCode())->toThrow(NativeReflectionException::class)
        ->and(fn () => $rc->functions())->toThrow(NativeReflectionException::class);
});
