# AGENTS.md

Single-package PHP library (`omega-mvc/serializable-closure`) that makes closures serializable, optionally HMAC-signed. Requires PHP ^8.4. All code lives in `src/Omega/SerializableClosure/` (PSR-4 `Omega\SerializableClosure\`), tests in `tests/` (PSR-4 `Tests\`, Pest).

## Commands

```sh
composer install              # dev deps: pest, phpstan, php_codesniffer
composer test                 # Pest suite with coverage (XDEBUG_MODE=coverage)
composer test-no-coverage     # Pest suite without coverage (XDEBUG_MODE=off)
composer phpstan              # level 10 over src/ + tests/
composer phpcs                # PSR-12 over src/ + tests/
vendor/bin/pest --filter=X    # single test
```

`composer.lock` and `/cache/` are gitignored — don't commit the lockfile, ignore untracked caches.

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
