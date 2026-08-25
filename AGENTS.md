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

## Coverage plateau (line coverage ~99%)

The remaining uncovered lines in `Support/ReflectionClosure.php` are not test gaps:

- `1038` — `file_get_contents() === false` race guard: the file was just stat-verified; no seam to trigger.
- `1437` — `withStringKeys()` non-iterable early return: `getStaticVariables()` always returns an array.
- `1278..1280` — T_NS_SEPARATOR inside a group-use: PHP grammar forbids leading-backslash entries in groups.
- `733..734`, `741..746` — tokenizer fallback arms unreachable with the PHP ≥ 8 token stream (identifiers arrive pre-collapsed as T_NAME_*; ternary `:` cannot follow an instanceof-context identifier).
- `1108`, `1140` — **attribution anomaly**: proven executed (disk-log instrumentation fires during coverage runs) yet reported uncovered. Do not chase them with more tests.

Related trap: `use \Class`, `use function \f` and `use const \C` collapse into one T_NAME_FULLY_QUALIFIED token that `fetchItems()` ignores, so leading-backslash imports resolve to a bare `\` prefix (asserted as such in `FetchScanProbe` tests).

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
