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
use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Support\ReflectionClosure;
use ReflectionException as NativeReflectionException;
use Tests\Fixtures\AttributeHost;
use Tests\Fixtures\ClassResolutionProbe;
use Tests\Fixtures\ExposedReflectionClosure;
use Tests\Fixtures\FetchScanProbe;
use Tests\Fixtures\Grouped\GroupHost;
use Tests\Fixtures\LazyClassProbe;
use Tests\Fixtures\LazyFunctionsProbe;
use Tests\Fixtures\MethodHost;
use Tests\Fixtures\MethodHostChild;
use Tests\Fixtures\Rich\RichHost;
use Tests\Fixtures\TokenizerEdgeCases;
use Tests\TestCase;

final class ReflectionClosureTest extends TestCase
{
    private static function reflect(Closure $closure): ReflectionClosure
    {
        return new ReflectionClosure($closure);
    }

    public function testDetectsPlainClosures(): void
    {
        $rc = self::reflect(function (): int {
            return 1;
        });

        $this->assertFalse($rc->isStatic());
        $this->assertFalse($rc->isShortClosure());
    }

    public function testDetectsStaticClosures(): void
    {
        $rc = self::reflect(static function (): int {
            return 2;
        });

        $this->assertTrue($rc->isStatic());
        $this->assertFalse($rc->isShortClosure());
    }

    public function testDetectsArrowFunctions(): void
    {
        $rc = self::reflect(fn (): int => 3);

        $this->assertFalse($rc->isStatic());
        $this->assertTrue($rc->isShortClosure());
    }

    public function testDetectsStaticArrowFunctions(): void
    {
        $rc = self::reflect(static fn (): int => 4);

        $this->assertTrue($rc->isStatic());
        $this->assertTrue($rc->isShortClosure());
    }

    public function testBoundClosureRequiresBindingButNotScope(): void
    {
        $rc = self::reflect((new MethodHost())->boundThis());

        $this->assertTrue($rc->isBindingRequired());
        $this->assertFalse($rc->isScopeRequired());
    }

    public function testSelfReferencesRequireScopeClass(): void
    {
        $rc = self::reflect((new MethodHost())->selfConstant());

        $this->assertTrue($rc->isScopeRequired());
        $this->assertFalse($rc->isBindingRequired());
    }

    public function testParentReferencesRequireScopeClass(): void
    {
        $rc = self::reflect((new MethodHostChild())->parentConstant());

        $this->assertTrue($rc->isScopeRequired());
    }

    public function testGetCodeExtractsClassicClosureBody(): void
    {
        $code = self::reflect(function (int $x): int {
            return $x + 1;
        })->getCode();

        $this->assertStringContainsString('function (int $x): int', $code);
        $this->assertStringContainsString('return $x + 1;', $code);
    }

    public function testGetCodeKeepsStaticKeyword(): void
    {
        $code = self::reflect(static function (): void {
        })->getCode();

        $this->assertStringStartsWith('static function', $code);
    }

    public function testGetCodeKeepsFnKeyword(): void
    {
        $code = self::reflect(fn (): string => 'arrow')->getCode();

        $this->assertStringStartsWith('fn (', $code);
    }

    public function testGetCodeReplacesFileAndDir(): void
    {
        $code = self::reflect(function (): array {
            return [__FILE__, __DIR__];
        })->getCode();

        $this->assertStringNotContainsString('__FILE__', $code);
        $this->assertStringNotContainsString('__DIR__', $code);
        $this->assertStringContainsString('.php', $code);
    }

    public function testGetCodeResolvesClosureMagicConstants(): void
    {
        $code = self::reflect(function (): array {
            return [__FUNCTION__, __METHOD__];
        })->getCode();

        $this->assertStringContainsString('{closure}', $code);
        $this->assertStringNotContainsString('__FUNCTION__', $code);
        $this->assertStringNotContainsString('__METHOD__', $code);
    }

    public function testGetCodeResolvesClassConstant(): void
    {
        $code = self::reflect((new RichHost())->classConstant())->getCode();

        $this->assertStringNotContainsString('__CLASS__', $code);
    }

    public function testGetCodeResolvesNamespace(): void
    {
        $code = self::reflect(function (): string {
            return __NAMESPACE__;
        })->getCode();

        // The test file declares the Tests\Unit\Support namespace; var_export
        // doubles the backslashes, so compare against its own escaping.
        $namespaceLiteral = substr((string) var_export('Tests\Unit\Support', true), 1, -1);

        $this->assertStringContainsString($namespaceLiteral, $code);
        $this->assertStringNotContainsString('__NAMESPACE__', $code);
    }

    public function testGetCodeResolvesTrait(): void
    {
        $code = self::reflect((new RichHost())->traitConstant())->getCode();

        $this->assertStringNotContainsString('__TRAIT__', $code);
        $this->assertStringContainsString('Colorable', $code);
    }

    public function testFirstClassCallableMethods(): void
    {
        $code = self::reflect((new MethodHost())->answer(...))->getCode();

        $this->assertStringStartsWith('function', $code);
        $this->assertStringContainsString('ANSWER', $code);
    }

    public function testFirstClassCallablesOfGlobalFunctions(): void
    {
        $rc = self::reflect(strlen(...));

        $this->assertSame('strlen', $rc->getName());
        $this->expectException(NativeReflectionException::class);
        $rc->getUseVariables();
    }

    public function testAttributesAreRendered(): void
    {
        $code = self::reflect((new AttributeHost())->scalarArgs())->getCode();

        $this->assertStringStartsWith('#[', $code);
        $this->assertStringContainsString('SensitiveParameter', $code);
        $this->assertStringContainsString('Deprecated', $code);
    }

    public function testNonScalarAttributeArgumentsAbort(): void
    {
        $rc = self::reflect((new AttributeHost())->nonScalarArgs());

        $this->expectException(NativeReflectionException::class);
        $this->expectExceptionMessage('non-scalar');
        $rc->getCode();
    }

    public function testConstructorAcceptsPreExtractedCode(): void
    {
        $rc = new ReflectionClosure(fn (): int => 5, 'fn (): int => 5');

        $this->assertSame('fn (): int => 5', $rc->getCode());
        $this->assertSame([], $rc->getUseVariables());
    }

    public function testEvalCreatedClosuresCannotBeTokenized(): void
    {
        $closure = eval('return fn (): int => 9;');

        if (! $closure instanceof Closure) {
            throw new Exception('Eval did not produce a closure.');
        }

        $this->expectException(NativeReflectionException::class);
        $this->expectExceptionMessage('Cannot read');
        self::reflect($closure)->getCode();
    }

    public function testUseVariablesExtractedByName(): void
    {
        $alpha = 1;
        $beta = 'two';
        $unused = 3.0;

        $uses = self::reflect(fn () => [$alpha, $beta, $unused])->getUseVariables();

        $this->assertSame(['alpha' => 1, 'beta' => 'two', 'unused' => 3.0], $uses);
    }

    public function testShortClosuresReportCapturedValues(): void
    {
        $value = 42;

        $this->assertSame(['value' => 42], self::reflect(fn (): int => $value + 0)->getUseVariables());
    }

    public function testRichFixtureExposesMetadata(): void
    {
        $host = new RichHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();
        $functions = $rc->functions();
        $constants = $rc->constants();
        $structures = $rc->structures();

        $this->assertArrayHasKey('arrayobject', $classes);
        $this->assertArrayHasKey('dt', $classes);
        $this->assertArrayHasKey('ak', $functions);
        $this->assertArrayHasKey('count_all', $functions);
        $this->assertSame('\\strlen', $functions['strlen']);
        $this->assertArrayHasKey('EOL', $constants);
        $this->assertSame('\\PHP_EOL', $constants['EOL']);

        $types = array_column($structures, 'type');
        $this->assertContains('interface', $types);
        $this->assertContains('trait', $types);
        $this->assertContains('class', $types);
        $this->assertContains('enum', $types);
        $this->assertCount(4, $structures);
    }

    public function testKitchenSinkClosureRoundTrip(): void
    {
        $host = new RichHost();

        $restored = unserialize(serialize(new SerializableClosure(
            $host->sink()
        )));

        // @phpstan-ignore-next-line callable.nonCallable
        $result = $restored();

        $this->assertIsString($result);
        $this->assertStringContainsString('|N|', $result);
    }

    public function testOneLineMethods(): void
    {
        $rc = self::reflect((new TrickyHost())->tricky());

        $this->assertStringContainsString('function', $rc->getCode());
        $this->assertStringNotContainsString('tricky', $rc->getCode());
    }

    public function testIsShortClosureMemoizesIndependently(): void
    {
        $rc = self::reflect(static fn (): int => 6);

        $this->assertTrue($rc->isShortClosure());
        $this->assertTrue($rc->isStatic());
    }

    public function testMultilineArrows(): void
    {
        $rc = self::reflect(fn (): array => [
            1,
            2,
        ]);

        $this->assertStringContainsString('=>', $rc->getCode());
        $this->assertStringContainsString('2', $rc->getCode());
    }

    public function testTrackmeCommentInjectsProvenanceHeader(): void
    {
        $host = new RichHost();

        $code = self::reflect($host->sink())->getCode();

        $this->assertStringContainsString('Date      : ', $code);
        $this->assertStringContainsString('Timestamp : ', $code);
    }

    public function testSelfPropSetsScopeRequired(): void
    {
        $rc = self::reflect(TokenizerEdgeCases::staticPropClosure());

        $this->assertTrue($rc->isScopeRequired());
        $this->assertStringContainsString('self::$counter', $rc->getCode());
    }

    public function testSelfClassSetsScopeRequired(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->selfClassClosure());

        $this->assertStringContainsString('self::class', $rc->getCode());
        $this->assertTrue($rc->isScopeRequired());
    }

    public function testUseClauseExtractsCapturedVariables(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->useClause(10));

        $this->assertArrayHasKey('a', $rc->getUseVariables());
        $this->assertArrayHasKey('b', $rc->getUseVariables());
        $this->assertStringContainsString('use ($a, $b)', $rc->getCode());
    }

    public function testArrowWithSpreadOperator(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->arrowWithSpread())->getCode();

        $this->assertStringStartsWith('fn', $code);
        $this->assertStringContainsString('...', $code);
    }

    public function testStaticArrowWithQualifiedReturnType(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->staticArrowQualified())->getCode();

        $this->assertStringStartsWith('static fn', $code);
        $this->assertStringContainsString('Closure\\Subspace\\QF', $code);
    }

    public function testAnonymousClassExtendingNamedClass(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->anonymousClassExtends());
        $code = $rc->getCode();

        $this->assertStringContainsString('new class extends', $code);
        $this->assertStringContainsString('greet', $code);
    }

    public function testNewSelfResolvesScopeClass(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->newSelfParent());

        $this->assertStringContainsString('new self()', $rc->getCode());
        $this->assertTrue($rc->isScopeRequired());
    }

    public function testStaticClassInStaticMethodClosure(): void
    {
        $rc = self::reflect(TokenizerEdgeCases::staticSelf());

        $this->assertStringContainsString('static::class', $rc->getCode());
    }

    public function testChainedMethodCalls(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->chainedCalls())->getCode();

        $this->assertStringContainsString('strtolower', $code);
        $this->assertStringContainsString('trim', $code);
    }

    public function testInstanceofCheck(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->instanceofCheck())->getCode();

        $this->assertStringContainsString('instanceof', $code);
    }

    public function testNamespaceQualifiedFunctionCall(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namespaceQualifiedCall())->getCode();

        $this->assertStringContainsString('Tests\\', $code);
    }

    public function testQualifiedNamesPreserved(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->qualifiedNames())->getCode();

        $this->assertStringContainsString('Closure\\Subspace\\QF', $code);
    }

    public function testGroupedImportsParsed(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();
        $functions = $rc->functions();
        $constants = $rc->constants();
        $structures = $rc->structures();

        $expectedClasses = ['arrayobject', 'userdefinedfixture', 'colorable', 'wearable', 'parentfixture', 'ah', 'mh'];
        foreach ($expectedClasses as $expectedClass) {
            $this->assertArrayHasKey($expectedClass, $classes);
        }

        foreach (['ak', 'cnt', 'slen', 'av', 'tnf', 'sl'] as $function) {
            $this->assertArrayHasKey($function, $functions);
        }

        foreach (['EOL', 'INTSIZE', 'FLOATDIG', 'PHPVER', 'MAJVER'] as $constant) {
            $this->assertArrayHasKey($constant, $constants);
        }

        $this->assertGreaterThanOrEqual(4, count($structures));
    }

    public function testGroupedFunctionAndConstantImports(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $functions = $rc->functions();
        $constants = $rc->constants();

        $this->assertSame('\\Tests\\Fixtures\\tokenizerNamedFunction', $functions['tnf']);
        $this->assertSame('\\strtolower', $functions['sl']);
        $this->assertSame('\\PHP_VERSION', $constants['PHPVER']);
        $this->assertSame('\\PHP_MAJOR_VERSION', $constants['MAJVER']);
    }

    public function testFileLevelNewAndInvokePaths(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $structures = $rc->structures();
        $types = array_column($structures, 'type');

        $this->assertContains('class', $types);
        $this->assertContains('interface', $types);
        $this->assertContains('trait', $types);
        $this->assertContains('enum', $types);
    }

    public function testNewVariableFollowedByMethodCall(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->anonymousClassExtends())->getCode();

        $this->assertStringContainsString('new class extends', $code);
    }

    public function testClosureBodyWithUseKeyword(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->useClause(5))->getCode();

        $this->assertStringContainsString('use ($a, $b)', $code);
    }

    public function testNsSeparatorInIdStart(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namespaceQualifiedCall())->getCode();

        $this->assertStringContainsString('\\Tests\\', $code);
    }

    public function testIsBindingRequiredDetectsThisUsage(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->anonymousClassExtends());

        $this->assertFalse($rc->isBindingRequired());
    }

    public function testIsStaticReturnsCachedValue(): void
    {
        $rc = self::reflect(TokenizerEdgeCases::staticPropClosure());

        $this->assertFalse($rc->isStatic());
        $this->assertFalse($rc->isShortClosure());
    }

    public function testStaticArrowIsStaticAndShort(): void
    {
        $rc = self::reflect(TokenizerEdgeCases::staticArrowQualified());

        $this->assertTrue($rc->isStatic());
        $this->assertTrue($rc->isShortClosure());
    }

    public function testStaticKeywordResetsToStart(): void
    {
        $rc = self::reflect(TokenizerEdgeCases::staticPropClosure());

        $this->assertStringStartsWith('function', $rc->getCode());
    }

    public function testNamedFunctionStateResets(): void
    {
        $host = new RichHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();
        $functions = $rc->functions();

        $this->assertNotEmpty($classes);
        $this->assertNotEmpty($functions);
    }

    public function testInvokeStateSkipsWhitespace(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->chainedCalls())->getCode();

        $this->assertStringContainsString('strtolower', $code);
    }

    public function testShortClosureWithDoubleArrow(): void
    {
        $code = self::reflect(fn (int $a): int => $a + 1)->getCode();

        $this->assertStringStartsWith('fn', $code);
        $this->assertStringContainsString('=>', $code);
    }

    public function testReturnTypeWithColon(): void
    {
        $code = self::reflect(function (): string {
            return 'x';
        })->getCode();

        $this->assertStringContainsString(': string', $code);
    }

    public function testNewStateSkipsWhitespace(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->anonymousClassExtends())->getCode();

        $this->assertStringContainsString('new class extends', $code);
    }

    public function testBeforeStructureStateCapturesClassName(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $structures = $rc->structures();
        $names = array_column($structures, 'name');

        $this->assertContains('GroupHost', $names);
        $this->assertContains('GroupInterface', $names);
        $this->assertContains('GroupTrait', $names);
        $this->assertContains('GroupSuit', $names);
    }

    public function testStructureStateTracksEndLine(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $structures = $rc->structures();

        foreach ($structures as $struct) {
            $this->assertGreaterThanOrEqual($struct['start'], $struct['end']);
        }
    }

    public function testUseGroupCommaSeparatedNames(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();

        $this->assertArrayHasKey('arrayobject', $classes);
        $this->assertArrayHasKey('userdefinedfixture', $classes);
        $this->assertArrayHasKey('colorable', $classes);
    }

    public function testUseGroupClosingBrace(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $functions = $rc->functions();

        $this->assertArrayHasKey('ak', $functions);
        $this->assertArrayHasKey('cnt', $functions);
        $this->assertArrayHasKey('slen', $functions);
    }

    public function testAliasStateCapturesAliasedName(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();

        $this->assertArrayHasKey('ah', $classes);
        $this->assertArrayHasKey('mh', $classes);
        $this->assertArrayHasKey('dt', $classes);
    }

    public function testUseGroupWithNameQualified(): void
    {
        $host = new GroupHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();

        $this->assertArrayHasKey('arrayobject', $classes);
    }

    public function testUseStatementWithLeadingBackslash(): void
    {
        $host = new RichHost();
        $rc = new ExposedReflectionClosure($host->sink());

        $classes = $rc->classes();

        $this->assertArrayHasKey('arrayobject', $classes);
        $this->assertStringStartsWith('\\', $classes['arrayobject']);
    }

    public function testNewStateWithDefaultToken(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->newSelfParent())->getCode();

        $this->assertStringContainsString('new self()', $code);
    }

    public function testIdNameWithColon(): void
    {
        $code = self::reflect(function (): string {
            return 'x';
        })->getCode();

        $this->assertStringContainsString(':', $code);
    }

    public function testIdNameDefaultResolvesConstants(): void
    {
        $code = self::reflect(function (): mixed {
            return \PHP_INT_SIZE;
        })->getCode();

        $this->assertStringContainsString('PHP_INT_SIZE', $code);
    }

    public function testIdStartVariableAfterNew(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->newSelfParent())->getCode();

        $this->assertStringContainsString('new self()', $code);
    }

    public function testClosureWithThisUsageSetsIsBindingRequired(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->anonymousClassExtends());

        $this->assertFalse($rc->isBindingRequired());
    }

    public function testRichFixtureClosureHasNoUncapturedMagicConstants(): void
    {
        $host = new RichHost();
        $code = self::reflect($host->sink())->getCode();

        $this->assertStringNotContainsString('__CLASS__', $code);
        $this->assertStringNotContainsString('__TRAIT__', $code);
        $this->assertStringNotContainsString('__FUNCTION__', $code);
        $this->assertStringNotContainsString('__METHOD__', $code);
    }

    public function testShortClosureEndingWithSemicolon(): void
    {
        $code = self::reflect(fn (): int => 42)->getCode();

        $this->assertStringContainsString('42', $code);
    }

    public function testShortClosureEndingWithClosingParen(): void
    {
        $code = self::reflect(fn (int $x): int => $x)->getCode();

        $this->assertStringContainsString('$x', $code);
    }

    public function testShortClosureEndingWithComma(): void
    {
        $code = self::reflect(fn (): array => [1, 2])->getCode();

        $this->assertStringContainsString('[1, 2]', $code);
    }

    public function testNameQualifiedTokenInBody(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->nameQualifiedInBody())->getCode();

        $this->assertStringContainsString('Colorable', $code);
    }

    public function testAnonymousClassWithUseTrait(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->anonymousClassWithTrait())->getCode();

        $this->assertStringContainsString('new class', $code);
        $this->assertStringContainsString('use', $code);
        $this->assertStringContainsString('Colorable', $code);
    }

    public function testImportedFunctionCall(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->importedFunctionCall())->getCode();

        $this->assertStringContainsString('tokenizerArraySort', $code);
    }

    public function testSameLineNamedFunctionResetsHunt(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namedFunctionSameLine())->getCode();

        $this->assertStringContainsString('function ()', $code);
        $this->assertStringNotContainsString('tokenizerEdgeProbeA1', $code);
    }

    public function testSameLineNamedFunctionFollowedByArrow(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namedFunctionBeforeArrow())->getCode();

        $this->assertStringContainsString('fn () => 3', $code);
        $this->assertStringNotContainsString('tokenizerEdgeProbeB2', $code);
    }

    public function testStaticQualifiedCallResetsHunt(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->staticQualifiedReset())->getCode();

        $this->assertStringContainsString('function ()', $code);
        $this->assertStringContainsString('return 4;', $code);
    }

    public function testPlainBracedClosure(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->plainBracedClosure())->getCode();

        $this->assertStringContainsString('{', $code);
        $this->assertStringContainsString('return 5;', $code);
    }

    public function testRelativeQualifiedNameInBody(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->relativeQualifiedNameInBody())->getCode();

        $this->assertStringContainsString('\Tests\Fixtures\Grouped\GroupInterface', $code);
    }

    public function testObjectOperatorAcrossLines(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->chainedAcrossLines())->getCode();

        $this->assertStringContainsString('$this', $code);
        $this->assertStringContainsString('describe', $code);
    }

    public function testOperatorAtEndOfLine(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->chainedWithOperatorEOL())->getCode();

        $this->assertStringContainsString('describe', $code);
    }

    public function testBracedStringAccessorAfterOperator(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->braceAccessorAfterOperator())->getCode();

        $this->assertStringContainsString("{'k'}", $code);
    }

    public function testNewWithVariableClassName(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->newVariableClass())->getCode();

        $this->assertStringContainsString('new $cls()', $code);
    }

    public function testNamedArgumentColons(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namedArgumentsCall())->getCode();

        $this->assertStringContainsString("strlen(string: 'abc')", $code);
    }

    public function testIsStaticMemoizesVerdict(): void
    {
        $rc = self::reflect((new TokenizerEdgeCases())->plainBracedClosure());

        $this->assertFalse($rc->isStatic());
        $this->assertFalse($rc->isStatic());

        $static = self::reflect(static fn (): int => 1);

        $this->assertTrue($static->isStatic());
        $this->assertTrue($static->isStatic());
    }

    public function testIsShortClosureStripsStaticPrefix(): void
    {
        $rc = self::reflect(static fn (): int => 2);

        $this->assertTrue($rc->isShortClosure());
        $this->assertTrue($rc->isShortClosure());
    }

    public function testIsStaticAndIsShortClosureBothCallOrders(): void
    {
        $shortFirst = self::reflect(static function (): int {
            return 8;
        });

        $this->assertFalse($shortFirst->isShortClosure());
        $this->assertTrue($shortFirst->isStatic());

        $staticFirst = self::reflect(static function (): int {
            return 9;
        });

        $this->assertTrue($staticFirst->isStatic());
        $this->assertFalse($staticFirst->isShortClosure());
    }

    public function testIsStaticAndIsShortClosurePropagateMissingSource(): void
    {
        $rc = new ExposedReflectionClosure(strlen(...));

        $this->assertThrows(NativeReflectionException::class, fn () => $rc->isStatic());
        $this->assertThrows(
            NativeReflectionException::class,
            fn (): bool => (new ExposedReflectionClosure(strlen(...)))->isShortClosure()
        );
    }

    public function testNamedFunctionFollowedByStaticClosure(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->namedFunctionBeforeStatic())->getCode();

        $this->assertStringContainsString('static function ()', $code);
        $this->assertStringNotContainsString('tokenizerEdgeProbeC4', $code);
    }

    public function testMagicConstantsInBodyResolve(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->magicConstantsInBody())->getCode();

        $this->assertStringContainsString("'Tests\\\\Fixtures\\\\TokenizerEdgeCases'", $code);
        $this->assertStringContainsString('{closure}', $code);
    }

    public function testMagicConstantsInsideAnonymousClass(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->magicConstantsInsideStructure())->getCode();

        $this->assertStringContainsString('__CLASS__', $code);
        $this->assertStringContainsString('__METHOD__', $code);
    }

    public function testTrackmeCommentRewritesToTimestampedBlock(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->trackmeComment())->getCode();

        $this->assertStringContainsString('/**', $code);
        $this->assertStringContainsString('* Date', $code);
    }

    public function testUseClausesCaptureByValueAndReference(): void
    {
        $byValue = self::reflect((new TokenizerEdgeCases())->useByValue())->getUseVariables();
        $byRef   = self::reflect((new TokenizerEdgeCases())->useByReference())->getUseVariables();

        $this->assertSame(['v'], array_keys($byValue));
        $this->assertSame(['v'], array_keys($byRef));
    }

    public function testAnonymousClassAncestry(): void
    {
        $code = self::reflect((new TokenizerEdgeCases())->anonymousRelativeAncestry())->getCode();

        $this->assertStringContainsString('extends \Tests\Fixtures\Suit', $code);
        $this->assertStringContainsString('implements \Tests\Fixtures\Grouped\GroupInterface', $code);
    }

    public function testNamespacedFunctionCallsResolve(): void
    {
        $code = self::reflect((new LazyFunctionsProbe())->callsNamespacedFunction())->getCode();

        $this->assertStringContainsString('\Tests\Fixtures\tokenizerNamedFunction', $code);
    }

    public function testParenthesizedNewTriggersLazyClasses(): void
    {
        $code = self::reflect((new LazyClassProbe())->instantiatesImportedClass())->getCode();

        $this->assertStringContainsString('new \Tests\Fixtures\Suit', $code);
    }

    public function testImportedClassesResolveToFqcn(): void
    {
        $code = self::reflect((new ClassResolutionProbe())->resolvesImportedClasses())->getCode();

        $this->assertStringContainsString('new \Tests\Fixtures\Suit', $code);
        $this->assertStringContainsString('\Tests\Fixtures\Suit::class', $code);
        $this->assertStringContainsString('new self()', $code);
        $this->assertStringContainsString('SOME_UNDEFINED_PROBE', $code);
    }

    public function testTraitResolvesThroughStructuresCache(): void
    {
        $consumer = new class {
            use \Tests\Fixtures\TraitProbe;
        };

        $code = self::reflect($consumer->traitConstClosure())->getCode();

        $this->assertStringContainsString("'TraitProbe'", $code);
    }

    public function testFileLevelScanCoversGroupedImports(): void
    {
        $probe = new FetchScanProbe();
        $rc    = new ExposedReflectionClosure($probe->probe());

        // Leading-backslash imports (class, function or const) collapse into a
        // single T_NAME_FULLY_QUALIFIED token that the scanner ignores, leaving
        // a bare "\" prefix behind; grouped unqualified imports resolve fully.
        $this->assertSame([
            's2',
            'wearable',
            'gi3',
            'arrayiterator',
            'splstack',
        ], array_keys($rc->classes()));
        $this->assertSame('\Tests\Fixtures\Grouped\GroupInterface', $rc->classes()['gi3']);
        $this->assertSame('\Tests\Fixtures\Suit', $rc->classes()['s2']);
        $this->assertSame([
            'cnt2' => '\\',
            'tas'  => '\Tests\Fixtures\tokenizerArraySort',
        ], $rc->functions());
        $this->assertSame([
            'EOL2' => '\\',
            'PC2'  => '\ProbeConsts\PROBE_CONST',
        ], $rc->constants());
    }

    public function testClosuresFromGlobalNamespace(): void
    {
        require_once __DIR__ . '/../../Fixtures/GlobalProbe.php';

        $code = self::reflect((new \GlobalProbe())->probe())->getCode();

        $this->assertStringContainsString('static function', $code);
        $this->assertStringContainsString('return 1', $code);
    }

    public function testClosuresWithoutSourceCodeRejected(): void
    {
        $rc = new ExposedReflectionClosure(strlen(...));

        // getCode() rejects first; the cache accessors share getHashedFileName()
        // and would reject through its own guard.
        $this->assertThrows(NativeReflectionException::class, fn () => $rc->getCode());
        $this->assertThrows(NativeReflectionException::class, fn () => $rc->functions());
    }
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
final class TrickyHost
{
    public static int $counter = 0;

    public function tricky(): Closure
    {
        static $memo = 0;

        return function () use ($memo): callable {
            if ($memo < 0) {
                // @phpstan-ignore-next-line function.inner
                function ghost(): void
                {
                }
            }

            return fn (): int => 5;
        };
    }
}
