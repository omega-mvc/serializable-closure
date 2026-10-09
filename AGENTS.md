# AGENTS.md — omega-mvc/serializable-closure

Serializes PHP closures, either unsigned (native) or HMAC-signed (namespace
`Omega\SerializableClosure\`). Standalone Composer package with its own git repo and CI; consumed by
`omega-mvc/framework` and the starter app from `vendor/`. Requires only `php ^8.4`.

## Commands

Run from this package directory. `composer` is the source of truth; the starter app's OpenCode config
denies `composer*`, so call the underlying binaries directly when blocked.

```bash
composer test              # XDEBUG_MODE=coverage vendor/bin/phpunit --coverage
composer test-no-coverage  # XDEBUG_MODE=off php vendor/bin/phpunit --no-coverage
composer phpstan           # XDEBUG_MODE=off php vendor/bin/phpstan analyse
composer phpcs             # mkdir -p cache/phpcs && XDEBUG_MODE=off php vendor/bin/phpcs
```

- Note `composer test` **runs coverage** here (unlike framework/gettext); use `test-no-coverage` for quick runs.
- Single test / filter: `vendor/bin/phpunit tests/Unit/SerializableClosureTest.php` or `--filter=round-trips`.
- Every script except `test` prefixes `XDEBUG_MODE=off`; do the same for direct tool calls.

## Tests

- **PHPUnit 13**. `autoload-dev` maps `Tests\` → `tests/` and loads `tests/Fixtures/GlobalProbe.php`.
  Layout: `tests/Unit/{Exception,Serializers,Signers,Support}/` plus `tests/Fixtures/`.
- `phpunit.xml.dist`: `failOnRisky`, `failOnWarning`, `pathCoverage="true"` → HTML `cache/coverage/`, and
  sets `OMEGA_STRESS_ITERATIONS=10`.
- **Stress tests** are tagged `--group=stress`. The budget comes from `Tests\TestCase::stressIterations()`:
  `OMEGA_STRESS_ITERATIONS` (used as-is) > `OMEGA_TEST_MODE=light` (10) > `CI`/`GITHUB_ACTIONS` (100) >
  local default (10000). They are skipped when the budget is ≤ 1. Override with
  `OMEGA_STRESS_ITERATIONS=500 vendor/bin/phpunit --group=stress`.
- Coverage expectations are documented in `README.md` ("Reading the coverage report honestly"): line
  coverage is 100%, but branches (~97–99%) and paths (<1%) are that way by design — never quote a
  single-run figure as exact. Xdebug ≤ 3.4.5 can crash *after* a fully green path-coverage run (upstream
  bug); rerun, split by file, or upgrade Xdebug.

## Static analysis / lint / docs

- PHPStan level 10 (config in `phpstan.neon.dist`, excludes `tests/Fixtures`). Several findings are tool
  limitations suppressed inline with `@phpstan-ignore-next-line` and explained in `README.md`;
  `composer phpstan` exits 0 — do not remove those suppressions or chase the drops.
- PHPCS: PSR-12, cache `cache/phpcs/phpcs.json`, excludes `tests/Fixtures/*`, disables
  `PSR1.Files.SideEffects` for `tests/*` and `PSR1.Methods.CamelCapsMethodName` for
  `src/Omega/SerializableClosure/Support/ClosureStream.php` (stream wrappers use snake_case method names).
- API docs: `phpdoc.xml.dist` exists but the PHAR ships separately — `phpDocumentor.phar -c phpdoc.xml.dist`
  writes to `cache/apiDoc`.
- Inherent PHP limits (not bugs) are listed in `README.md`: by-reference closure parameters and
  anonymous-class captures cannot be serialized.
- Changes here belong to this package's git repo: commit them here, or they are lost on `composer update`.
- CI: `.github/workflows/{tests,coding-standard,static-analysis}.yml`.
