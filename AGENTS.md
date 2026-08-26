# AGENTS.md

Single-package PHP library (`omega-mvc/serializable-closure`) that makes closures serializable, optionally HMAC-signed. Requires PHP ^8.4. All code lives in `src/Omega/SerializableClosure/` (PSR-4 `Omega\SerializableClosure\`), tests in `tests/` (PSR-4 `Tests\`, Pest).

## Commands

```sh
composer install              # dev deps: pest, phpstan, php_codesniffer
composer test                 # Pest suite with coverage (XDEBUG_MODE=coverage)
composer test-no-coverage     # Pest suite without coverage (XDEBUG_MODE=off)
composer phpstan              # level 10 over src/ + tests/
composer phpcs                # PSR-12 over src/ + tests/
vendor/bin/pest --filter=X    # single test / filter
XDEBUG_MODE=coverage vendor/bin/pest --coverage   # HTML report in cache/coverage/
```

- Plain `vendor/bin/pest` fails without a coverage driver: `phpunit.xml` declares an HTML coverage report. Always pass `--no-coverage` unless you actually want coverage.
- All scripts run with `XDEBUG_MODE=off`. `composer.lock`, `/cache/`, and local `phpcs.xml`/`phpunit.xml` are gitignored; those local copies shadow the `.dist` files, so editing `phpcs.xml.dist` or `phpunit.xml.dist` won't affect local runs until the shadow is removed (`phpstan.neon` has no local copy).
- **Pest v5 exits 1 even on green runs** if a test file triggers any PHP notice at include time — classically `use Closure;`-style imports of *global* names in namespace-less files ("non-compound name has no effect"). Test files directly under `tests/Unit/` have **no** namespace; subdirectory suites declare `Tests\Unit\…`, where those imports are fine and used (e.g. `ReflectionClosureTest` aliases global `ReflectionException`).
- **Pest dataset rows must be positional argument lists** (`[payload, expected]`, optionally keyed by case name for output): rows written as associative maps keep passing their assertions yet still produce the exit-code-1 symptom above.
- **Coverage runs are xdebug-heavy**: plain `--path-coverage` on the whole Support suite can segfault (`double free or corruption`); prefer the default `vendor/bin/pest --coverage`, and if it aborts, rerun or split by suite file.
- **The segfaults are an xdebug bug, not ours**: they hit after all tests pass, while xdebug frees its path-coverage structures at report/shutdown time (same family as xdebug #2332/#1486). Reproduced on pristine sources, ~50% of full-suite runs, never caused by test code. Installed xdebug 3.4.5; upstream ships 3.4.6/3.4.7/3.5.x with further crash fixes — upgrading (`pecl upgrade xdebug`) is the real fix. Deleting `cache/` does *not* prevent them (verified experimentally); `USE_ZEND_ALLOC=0` makes single-file runs stable but not the full suite.

## Line coverage 100% (by design)

`Support/ReflectionClosure.php` reaches 100% because dead defensive code was removed deliberately:

- The four per-file cache getters (`getClasses/getFunctions/getConstants/getStructures`) delegate to one `scanCache()` holding the single `fetchItems()` call site — `fetchItems()` populates every slot atomically. Before consolidation, two of the four lazy-fetch lines executed but were never credited by xdebug/php-code-coverage (attribution anomaly); a single call site made the whole class measurable.
- `getFileTokens()` keeps the eval-path `is_file()` rejection but no longer guards `file_get_contents() === false`: a read racing an unlink degrades to empty tokens, which the tokenizer rejects downstream.
- `ReflectionClosure::withStringKeys()` takes an `array` (its only caller passes `getStaticVariables()`); the non-iterable early return was unreachable. (`Native::withStringKeys()` keeps its mixed-typed copy.)
- Tokenizer arms that PHP ≥ 8 cannot reach were deleted: `id_start`'s default-reprocess fallback and the `use-group` `T_NS_SEPARATOR` case (leading-backslash entries are a grammar error inside groups).
- `id_name`'s `:` handling is unconditional (named arguments and goto labels flow through it); the old `lastState === 'closure' && $context === 'root'` else-path was grammar-shadow.
- `$nsf` no longer special-cases a leading-backslash namespace: `getNamespaceName()` can never return one.
- `getHashedFileName()` caches the validated file name in `$fileName`; `getFileTokens()` reads it without re-validating.

Do not reintroduce these guards without re-opening the coverage question.

### Branch / path coverage reality check

Lines sit at 100%; the other report metrics do not, **deliberately**: `pathCoverage="true"` stays on — reporting the ugly numbers beats hiding them.

- **Branches fluctuate between runs on identical code** (observed 97.4%–98.5%): the handful of persistent "warning" rows are compilation artifacts, proven by opcode dump (`php -d opcache.opt_debug_level=0x20000`):
  - *String state dispatches compile to `SWITCH_STRING`* (hash lookup, one per state machine): a case label is jumped to or not — the sequential "evaluated and skipped" outcome xdebug's branch model expects no longer exists at bytecode level (`case 'anonymous':`, `case 'structure':`).
  - *Imported function calls compile to two paths*: `JMP_FRAMELESS` fast-path plus an `INIT_NS_FCALL_BY_NAME` fallback that can never run when the frameless variant exists — every call to a `use function`-imported helper carries one dead arc attributed to its source line (`isStatic`, `isShortClosure`, `$use[]` push, `#trackme`, `$_method` ternary).
  - Instrumented runs prove both *semantic* outcomes execute on every flagged line (e.g. `isStatic`: 10× true, 6× false, 4× cache-skip on disk while still flagged). Two arcs (`case T_CLASS:` in `id_start`, `case 'anonymous':`) additionally lost their reachable skip-token when the `default:` arm was deleted.
  - Two arcs (`case T_CLASS:` in `id_start`, `case 'anonymous':`) lost their skip-outcome when the unreachable `default:` arm was deleted; no valid token falls through those comparisons anymore. Never quote a single-run branch figure as exact.
- **Paths (~0.5%)**: `getCode()` alone exposes 4096 = 2^12 paths from ~12 binary decision points — the count is arithmetic on the state machine, not a backlog. Covering them is combinatorial, not testable by curation.
- **Functions/methods & classes** trail the path number in path-coverage mode (methods require every arc of the method; `getCode()`/`fetchItems()` can therefore never read "covered"). Observed methods range across runs: 75%–89%. They are not an independent signal of untested code.

## Hard-won facts

- **Stream-wrapper methods must stay snake_case** (`stream_open`, ...) in `Support\ClosureStream`: the engine looks them up by exact name. That's why `phpcs.xml.dist` excludes `PSR1.Methods.CamelCapsMethodName` for that file — don't "fix" those names to camelCase, it breaks every unserialize.
- **HMAC verification is fail-closed**: `Signed::__unserialize()` throws `MissingSecretKeyException` when no signer is set, `InvalidSignatureException` on bad signature. The key must be set before serialize *and* unserialize.
- **`SerializableClosure` payload is `{serializable}` only**: nested/self-referencing closures are wrapped by `Native::mapByReference()` itself, and the transform hook applies solely at the `Native` layer. The old SC-side `uses`/anonymous-class layer is gone (captured *anonymous* classes are not serializable, mirroring PHP's own limitation).
- **Reflection-walking duplication lives in one generator**: `Native::userDefinedProperties()` feeds both `wrapClosures()` and `mapByReference()`.
- **Never reorder `mapPointers()` before `extract()`**: SelfReference slots must be rewritten (`=& $this->closure`) before symbols are imported with `EXTR_REFS`. Also note: re-binding through a by-ref parameter (`$param = &$other`) does NOT propagate to the caller's array slot — only direct slot writes (`$data[$key] = &$x`) do.
- `ReflectionClosure` tokenizes by line ranges: closures using `$this`/`self::` written on the same line as their enclosing method are mis-parsed (upstream limitation). Write test fixtures multi-line.
- `Native::$closure` is nullable during reconstruction; use `getClosure()` which throws if unset.
- **PHPStan 2 known limitations in tests**: `callable.nonCallable` on `unserialize()`-returned `mixed` (type-guarded with `instanceof`), and `function.inner` for named functions inside closures (tokenizer fixture). Both suppressed with `@phpstan-ignore` — not library bugs.

## Conventions

- Every file opens with the standard docblock header (`@category/@package/@link/@author/@copyright/@license/@version`) followed by `declare(strict_types=1);` — copy from an existing file.
- PHPDoc array shapes everywhere (level 10 requires them); keep lines ≤ 120 chars; use class-level `@phpstan-type` aliases for shapes reused in signatures.
- No suppressions (`@phpstan-ignore`, baselines) and no inline `@var` overrides: fix root causes. The only exceptions are the two known PHPStan 2 limitations documented above.
- `phpstan.neon.dist` and `phpcs.xml.dist` both exclude `tests/Fixtures/`: fixtures are intentionally dense test data, exempt from level 10 and PSR-12. Don't "clean them up" to satisfy a sniff that never runs on them.
