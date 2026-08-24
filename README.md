<p align="center">
    <a href="https://omega-mvc.github.io" target="_blank">
        <img src="https://github.com/omega-mvc/omega-assets/blob/main/images/logo-omega.png" alt="Omega Logo">
    </a>
</p>

<h1 align="center">
    Serializable Closure Package
</h1>

<p align="center">
    <a href="https://omega-mvc.github.io">Documentation</a> |
    <a href="https://github.com/omega-mvc/serializable-closure/blob/main/CHANGELOG.md">Changelog</a> |
    <a href="https://github.com/omega-mvc/serializable-closure/blob/main/CONTRIBUTING.md">Contributing</a> |
    <a href="https://github.com/omega-mvc/serializable-closure/blob/main/CODE_OF_CONDUCT.md">Code Of Conduct</a> |
    <a href="https://github.com/omega-mvc/serializable-closure/blob/main/LICENSE">License</a>
</p>

<p align="center">
    <a href="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml"><img src="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
    <a href="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml"><img src="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml/badge.svg?label=Pest" alt="Pest"></a>
    <a href="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml"><img src="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml/badge.svg?label=PHPCS" alt="PHPCS"></a>
    <a href="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml"><img src="https://github.com/omega-mvc/serializable-closure/actions/workflows/ci.yml/badge.svg?label=PHPStan" alt="PHPStan"></a>
</p>

# Omega - Serializable Closure

## Overview

Omega - Serializable Closure is a powerful and flexible library designed to provide robust serialization capabilities for PHP closures. It addresses the inherent challenges of serializing closures, especially those with complex scopes and bound contexts, while also introducing enhanced security features through cryptographic signatures. This library is meticulously crafted to leverage modern PHP features and ensure the integrity and safety of your serializable closures.

## Key Features

*   **Native Serialization:** Efficiently serializes closures using PHP's native mechanisms when cryptographic signatures are not required.
*   **Signed Serialization (HMAC):** Implements secure serialization by generating and verifying HMAC (Hash-based Message Authentication Code) signatures for closure data. This ensures the integrity and authenticity of serialized closures, preventing tampering.
*   **PHP 8.4+ Modern Features:** Fully embraces modern PHP features including:
    *   **Readonly Properties:** Leverages readonly properties where appropriate for improved immutability and thread safety.
    *   **Native Types:** Employs strict native type hints for enhanced code clarity and robustness.
    *   **`#[AllowDynamicProperties]`:** Utilizes this attribute for compatibility with dynamic property scenarios.
*   **Customizable Variable Transformation:** Provides hooks (`transformUseVariablesUsing` and `resolveUseVariablesUsing`) to customize how closure's `use` variables are transformed during serialization and deserialization.
*   **Advanced Reflection:** Employs a sophisticated `ReflectionClosure` to deeply analyze closure code, identify static variables, and extract necessary metadata for accurate serialization.

## Requirements

*   **PHP 8.4+**

## Installation

To install Omega - Serializable Closure, you can use Composer:

```bash
composer require omega-mvc/serializable-closure
```

## Usage

### Native Serialization (Unsigned)

For scenarios where cryptographic integrity is not a primary concern, you can use unsigned serialization.

```php
<?php

use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\UnsignedSerializableClosure;

// Create a closure
$closure = function (int $a, int $b): int {
    return $a + $b;
};

// Create an unsigned serializable closure
$unsignedSerializable = new UnsignedSerializableClosure($closure);

// Serialize the closure
$serialized = serialize($unsignedSerializable);

// Unserialize the closure
$unserializedSerializable = unserialize($serialized);

// Invoke the unserialized closure
$result = $unserializedSerializable(5, 3); // $result will be 8

echo "Native Serialization Result: " . $result . PHP_EOL;

// Alternatively, using the main SerializableClosure class without a secret key
SerializableClosure::setSecretKey(null); // Ensure no secret key is set for native serialization
$serializableNative = new SerializableClosure($closure);
$serializedNative = serialize($serializableNative);
$unserializedNative = unserialize($serializedNative);
$resultNative = $unserializedNative(10, 7); // $resultNative will be 17

echo "Native Serialization (via SerializableClosure) Result: " . $resultNative . PHP_EOL;
?>
```

### Signed Serialization (HMAC)

For enhanced security, it's recommended to use signed serialization. This requires setting a secret key.

```php
<?php

use Omega\SerializableClosure\SerializableClosure;
use Omega\SerializableClosure\Exception\InvalidSignatureException;
use Omega\SerializableClosure\Exception\MissingSecretKeyException;

// Set a secret key for HMAC signing
$secretKey = 'your_super_secret_key';
SerializableClosure::setSecretKey($secretKey);

// Create a closure with bound variables
$multiplier = 2;
$closureWithBound = function (int $number) use ($multiplier): int {
    return $number * $multiplier;
};

// Create a signed serializable closure
$signedSerializable = new SerializableClosure($closureWithBound);

// Serialize the closure
$serializedSigned = serialize($signedSerializable);

// --- Simulate receiving and unserializing the closure elsewhere ---

// At the receiving end, the secret key must be the same
SerializableClosure::setSecretKey($secretKey);

try {
    $unserializedSignedSerializable = unserialize($serializedSigned);
    $resultSigned = $unserializedSignedSerializable(10); // $resultSigned will be 20
    echo "Signed Serialization Result: " . $resultSigned . PHP_EOL;
} catch (InvalidSignatureException $e) {
    echo "Error: Invalid signature detected. The serialized closure may have been tampered with." . PHP_EOL;
} catch (MissingSecretKeyException $e) {
    echo "Error: Secret key is missing for signature verification." . PHP_EOL;
}

// --- Example of signature verification failure ---
echo "\nSimulating tampered data:" . PHP_EOL;
$tamperedSerialized = $serializedSigned;
// Tamper with the data (e.g., change a character in the serialized string)
$tamperedSerialized = str_replace('use ($multiplier', 'use ($unrelatedVariable', $tamperedSerialized);

SerializableClosure::setSecretKey($secretKey); // Re-set key for verification
try {
    $unserializedTampered = unserialize($tamperedSerialized);
    $resultTampered = $unserializedTampered(10);
    echo "Tampered Result (should not be reached): " . $resultTampered . PHP_EOL;
} catch (InvalidSignatureException $e) {
    echo "Successfully caught invalid signature for tampered data: " . $e->getMessage() . PHP_EOL;
}

// --- Example of missing secret key ---
echo "\nSimulating missing secret key:" . PHP_EOL;
SerializableClosure::setSecretKey(null); // No secret key set
try {
    unserialize($serializedSigned);
} catch (MissingSecretKeyException $e) {
    echo "Successfully caught missing secret key error: " . $e->getMessage() . PHP_EOL;
}

?>
```

## Security

The signed serialization mechanism utilizes **HMAC (Hash-based Message Authentication Code)** to ensure the integrity of serialized closures. When a secret key is provided using `SerializableClosure::setSecretKey()`, the library generates an HMAC signature of the serialized closure data. This signature is stored alongside the serialized data.

Upon deserialization, the library recalculates the HMAC signature using the same secret key and compares it with the provided signature. If the signatures do not match, an `InvalidSignatureException` is thrown, indicating that the serialized data may have been modified or corrupted since it was originally stored. This provides a crucial layer of defense against potential code injection or unauthorized modification of closures during transit or storage.

**Fail-closed by design:** loading signed data without the secret key throws `MissingSecretKeyException` instead of silently skipping verification. The key must be set before *both* serializing and unserializing signed closures.

**Important:**
*   Always use a strong, unique, and securely managed secret key.
*   Ensure the same secret key is used for both serialization and deserialization.
*   Without a key at construction time, `SerializableClosure` falls back to native unsigned serialization; `UnsignedSerializableClosure` always stays unsigned.
*   Serialization without a key on a *signed* closure (key removed after construction) throws `MissingSecretKeyException`.

## Technical Architecture

Omega - Serializable Closure employs a sophisticated internal architecture to handle the complexities of closure serialization:

### `ClosureStream`

This component registers a custom PHP stream wrapper (`omega-serializable-closure://`). This stream allows the library to represent the closure's source code as a stream, which is then `include`d during deserialization. This method is an efficient way to reconstruct the closure from its string representation.

*   **Purpose:** To enable the `include` mechanism for deserializing the closure code generated by `ReflectionClosure`.
*   **Mechanism:** It intercepts `include` calls for URLs starting with `omega-serializable-closure://`, effectively turning the provided code string into an executable PHP script.

### `ReflectionClosure`

This class extends PHP's native `ReflectionFunction` and provides advanced introspection capabilities for closures. It's instrumental in:

*   **Extracting Closure Code:** It parses the closure's source code to identify and extract key components.
*   **Identifying `use` Variables:** It precisely determines which variables are captured by the closure's `use` keyword, enabling their proper serialization.
*   **Detecting Binding Requirements:** It can ascertain if the closure relies on `$this` (bound object) or a specific scope, which is critical for correct `bindTo` operations.
*   **Handling Static Variables:** It provides mechanisms to serialize static variables that might be used within closures.
*   **Code Parsing:** It leverages PHP's tokenizer (`token_get_all`) to analyze the closure's syntax, understand its structure, and extract metadata without directly executing the code.

These components work in concert to provide a robust, secure, and flexible solution for serializing PHP closures, ensuring their integrity and faithful reconstruction.

## Testing

The test suite runs on [Pest](https://pestphp.com):

```sh
composer test
```

Tests live in `tests/` (PSR-4 `Tests\`), with the suite configuration in `phpunit.xml` — Pest reads PHPUnit's configuration. To run a single file or filter:

```sh
vendor/bin/pest tests/Unit/SerializableClosureTest.php
vendor/bin/pest --filter=round-trips
```

### Code Coverage

Coverage requires Xdebug (`xdebug.mode=coverage`) or PCOV:

```sh
XDEBUG_MODE=coverage vendor/bin/pest --coverage
```

## Static Analysis

```sh
composer phpstan    # PHPStan level 10 over src/ and tests/
composer phpcs      # PSR-12 over src/ and tests/
```

Both must pass before committing; `cache/` holds their result caches and is gitignored.

## Generating API Documentation with phpDocumentor

phpDocumentor is configured via `phpdoc.xml.dist` but the PHAR is not bundled. Install it separately and run:

```sh
phpDocumentor.phar -c phpdoc.xml.dist
```

Output lands in `cache/apiDoc`.

## Troubleshooting

All composer scripts are prefixed with `XDEBUG_MODE=off` to avoid noisy xdebug output. When invoking the underlying tools directly, prefix them the same way:

```sh
XDEBUG_MODE=off php vendor/bin/phpstan analyse
```

## Official Documentation

The official documentation for Omega is available [here](https://omega-mvc.github.io)

## Contributing

If you'd like to contribute to the OmegaMVC Serializable Closure package, please follow our [contribution guidelines](CONTRIBUTING.md).

## License

This project is open-source software licensed under the [GNU General Public License v3.0](LICENSE).
