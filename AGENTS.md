# AGENTS.md — Omega Serializable Closure

PHP 8.4+ package (namespace `Omega\SerializableClosure\`) that allows closures
to be serialized and stored, enabling safe use in persistent storage or across
requests. Not an application — a standalone Composer package, no runtime
dependencies (requires only `php ^8.4`).

## Commands

```bash
composer run phpcs          # phpcs (PSR-12, src/ + tests/; pre-creates cache/phpcs)
composer run phpcbf         # phpcbf auto-fix
composer run phpstan        # PHPStan level 10 (src/ + tests/), part of the workflow
composer run test           # pest WITH coverage (needs Xdebug/PCOV)
composer run test-no-coverage # pest WITHOUT coverage (CI runs this)
vendor/bin/pest tests/Unit/SerializableClosureTest.php     # a single file
vendor/bin/pest --filter=round-trips                       # round-trip tests
vendor/bin/pest --group=stress                             # stress tests
```

There is NO `check`/`ci` composer script. CI is GitHub Actions
(`.github/workflows/`: tests.yml, coding-standard.yml, static-analysis.yml, plus
ci.yml wiring them), always on PHP 8.4. Lint before test (`composer run phpcbf`
first); run `composer run phpstan` as part of verification.

## Code Style

- **PSR-12**, 4-space indent, UTF-8, LF line endings
- **Every source AND test file carries the full GPL-3.0 "Part of Omega"
  docblock header** — starts `<?php` → blank line → full docblock (Part of
  Omega - Serializable Closure Package. / @author Adriano Giovannini /
  @copyright 2024-2025 / @license GPL-3.0+ / @version ...) → blank →
  `declare(strict_types=1);` → namespace. Keep this header on every new file
  (unlike gettext, which has no header)
- `tests/Fixtures/*` — dense multi-symbol files by design, excluded from lint;
  do not reformat them
- `PSR1.Files.SideEffects` excluded for `tests/*` (eval-based fixtures and
  procedural dataset builders)
- `PSR1.Methods.CamelCapsMethodName` excluded **only** for
  `src/Omega/SerializableClosure/Support/ClosureStream.php`: PHP stream-wrapper
  protocol methods must be exact snake_case (`stream_open`, `stream_read`,
  `stream_eof`, `stream_set_option`, `stream_stat`, `url_stat`, `stream_seek`)
- Do NOT add an `<exclude-pattern>vendor/*</exclude-pattern>` to phpcs.xml.dist:
  this package is checked out nested inside another project's `vendor/` (the
  omega-mvc/omega starter), so a relative `vendor/*` pattern would match that
  enclosing path and exclude every file

## Structure

- `src/Omega/SerializableClosure/` — entry points `SerializableClosure.php`
  (signed mode, driven by `SerializableClosure::setSecretKey()`) and
  `UnsignedSerializableClosure.php` (no signature)
- `Serializers/` — `Native`, `Signed`, and `SerializableInterface`
- `Signers/` — `Hmac` and `SignerInterface`
- `Support/` — `ClosureScope`, `ClosureStream`, `ReflectionClosure`,
  `SelfReference`. `ClosureStream` registers the custom stream wrapper
  `omega-serializable-closure://`; closure source is represented as a stream and
  `include`d during deserialization
- `Exception/` — `InvalidSignatureException`, `MissingSecretKeyException`
- PHP 8.4 features in use: readonly classes/properties, native types,
  `#[AllowDynamicProperties]`, property hooks where applicable
- Hooks for transforming/resolving use-variables exist:
  `transformUseVariablesUsing` / `resolveUseVariablesUsing`

## Testing

- Pest 5 over PHPUnit. `tests/Pest.php` does
  `pest()->extend(Tests\TestCase::class)->in(__DIR__)`. NO `tests/bootstrap.php`
  exists — phpunit bootstrap is `vendor/autoload.php`; do NOT create one
- `tests/TestCase.php` is the shared base: `abstract class TestCase extends
  PHPUnit\Framework\TestCase` with `static stressIterations(): int`
- **Stress budget** (`stressIterations()`), resolved at runtime inside test
  bodies (never in Pest.php — env vars are loaded too late there):
  - `OMEGA_STRESS_ITERATIONS` set and positive → that value
  - `OMEGA_TEST_MODE=light` → 10
  - `CI` or `GITHUB_ACTIONS` set → 100
  - local default → 10000
  Stress tests are skipped automatically when the budget is ≤ 1. Control with
  `OMEGA_STRESS_ITERATIONS=500 vendor/bin/pest --group=stress`
- `phpunit.xml.dist`: `failOnRisky=true`, `failOnWarning=true`,
  `displayDetailsOnTestsThatTrigger*` all true, path coverage →
  `cache/coverage/`
- **Round-trips WORK today.** A stale NOTE in
  `tests/Unit/SerializableClosureTest.php` claims "unserialization round-trips
  are currently broken by the stream wrapper method naming issue documented in
  AGENTS.md" — that is obsolete: `--filter=round-trips` passes (5 tests),
  covering native and signed serialize/unserialize round-trips plus
  self-references. Do not propagate the stale claim; correct it if rewriting
  that file
- `phpstan.neon.dist`: level 10, `tmpDir: cache/phpstan`, excludes
  `tests/Fixtures`

## Notes

- `composer.lock` is gitignored/untracked; `cache/` is gitignored. The full
  package `.gitignore` ignores vendor/, composer.lock, package-lock.json,
  phpcs.xml, phpstan.neon, phpunit.xml, /cache/
- Known upstream issue: xdebug ≤ 3.4.5 may abort full-suite path-coverage runs
  (xdebug bug family #2332) — use `--no-coverage` locally if hit
- Verify every API claim against the real `src/`, never from memory