# AGENTS.md

Single-package PHP library (`omega-mvc/serializable-closure`) that makes closures serializable, optionally HMAC-signed. Requires PHP ^8.4. All code lives in `src/Omega/SerializableClosure/` (PSR-4 `Omega\SerializableClosure\`). There are **no tests and no CI** in this repo.

## Commands

```sh
composer install        # dev deps only (squizlabs/php_codesniffer); vendor/ not committed
mkdir -p cache/phpcs    # REQUIRED once; phpcs errors out if this dir is missing
composer phpcs          # the only script defined in composer.json (PSR12 against src/)
```

- `composer phpcs` currently exits 0 on the whole codebase — any new error you introduce will fail it.
- `cache/` is **not** gitignored; running phpcs leaves an untracked `cache/` dir behind.
- README/CONTRIBUTING reference `composer test`, `composer phpunit`, `composer phpstan`, `composer check-style`, `composer phpdoc` — **none of these scripts exist**. Docs are stale/copied from sibling Omega packages; trust `composer.json`.
- Since there is no test suite, verify behavior changes by writing a throwaway PHP script that round-trips closures through `serialize()`/`unserialize()`.

## Architecture

- `SerializableClosure::__construct()` picks the serializer from global static state: if `SerializableClosure::setSecretKey()` was called it uses `Serializers\Signed` (HMAC via `Signers\Hmac`), otherwise `Serializers\Native`. The secret key must be set identically before `unserialize()`, or tampering throws `Exception\InvalidSignatureException` / missing key throws `Exception\MissingSecretKeyException`.
- `UnsignedSerializableClosure` always uses native serialization regardless of secret key.
- On unserialization, closure source is reconstructed by `Support\ReflectionClosure` (tokenizer-based parsing) and executed via the custom stream wrapper registered by `Support\ClosureStream::register()` under protocol `omega-serializable-closure://`. Stream wrappers register once per process — beware side effects when scripting round-trips.
- Extension hooks are static too: `transformUseVariablesUsing()` / `resolveUseVariablesUsing()` mutate static props on `Native`.

## Conventions

- Every file opens with the standard docblock header (`@category/@package/@link/@author/@copyright/@license/@version`) followed by `declare(strict_types=1);` — copy from an existing file for new ones.
- PHPCS = PSR12 minus CamelCaps-method-name and file-header-grouping sniffs; match surrounding style rather than reformatting.
