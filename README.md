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
    <a href="https://github.com/omega-mvc/omega-mvc.github.io/blob/main/README.md#changelog">Changelog</a> |
    <a href="https://github.com/omega-mvc/omega/blob/main/CONTRIBUTING.md">Contributing</a> |
    <a href="https://github.com/omega-mvc/omega/blob/main/CODE_OF_CONDUCT.md">Code Of Conduct</a> |
    <a href="https://github.com/omega-mvc/omega/blob/main/LICENSE">License</a>
</p>

# Omega - Serializable Closure

## Overview

Omega - Serializable Closure is a powerful and flexible library designed to provide robust serialization capabilities for PHP closures. It addresses the inherent challenges of serializing closures, especially those with complex scopes and bound contexts, while also introducing enhanced security features through cryptographic signatures. This library is meticulously crafted to leverage modern PHP features and ensure the integrity and safety of your serializable closures.

## Key Features

*   **Native Serialization:** Efficiently serializes closures using PHP's native mechanisms when cryptographic signatures are not required.
*   **Signed Serialization (HMAC):** Implements secure serialization by generating and verifying HMAC (Hash-based Message Authentication Code) signatures for closure data. This ensures the integrity and authenticity of serialized closures, preventing tampering.
*   **Anonymous Class Support:** Seamlessly handles the serialization and deserialization of anonymous classes used within closures.
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

Upon deserialization, the library recalculates the HMAC signature using the same secret key and compares it with the provided signature. If the signatures do not match, an `InvalidSignatureException` is thrown, indicating that the serialized data may have been modified or corrupted since it was originally serialized. This provides a crucial layer of defense against potential code injection or unauthorized modification of closures during transit or storage.

**Important:**
*   Always use a strong, unique, and securely managed secret key.
*   Ensure the same secret key is used for both serialization and deserialization.
*   If no secret key is set, the serialization will fall back to native, unsigned serialization, and signature verification will be skipped.

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
*   **Handling Static Variables and Anonymous Classes:** It provides mechanisms to serialize and deserialize complex structures that might be used within closures.
*   **Code Parsing:** It leverages PHP's tokenizer (`token_get_all`) to analyze the closure's syntax, understand its structure, and extract metadata without directly executing the code.

These components work in concert to provide a robust, secure, and flexible solution for serializing PHP closures, ensuring their integrity and faithful reconstruction.

## Analysis

### Static Code Analysis with PHPStan

To run static analysis with `PHPStan`, use the command:

```sh
composer phpstan
```

### Static Code Analysis with Code Sniffer

To check the code with `Code Sniffer`, run the command:

```sh
composer phpcs
```

## Generating API Documentation with phpDocumentor

To generate the documentation, run the command.

```sh
composer phpdoc
```

> Make sure you have the `phpDocumentor.phar 3.5+` executable installed in the `vendor/bin` directory.

## Testing

### Running Unit Tests with PHPUnit

To run the tests with `PHPUnit`, type the command:

```sh
composer phpunit
```

> Note that the command above will run tests for the classes contained in the `app` and `vendor/omega-mvc` directories.

### Generating Code Coverage Reports

Omega supports code coverage with, requiring `xdebug` to be installed and configured on your system.

Here’s a basic working `xdebug` configuration for `Ubuntu 24.04`:

```sh
// File name: /etc/php/your_php_version/mods_available/xdebug.ini

zend_extension=xdebug.so
xdebug.show_exception_trace=0
xdebug.mode=coverage
zend_assertion=1
assert.exception=1
```

In accordance with the `phpunit` documentation, you should also ensure that the `error_reporting` and `memory_limit` variables are set as follows in the `/etc/php/your_php_version/cli/php.ini` file:

```sh
error_reporting=-1
memory_limit=-1
```

For more information, you can refer to the official documentation of [phpunit](docs.phpunit.de/en/11.4/installation.html)

### Troubleshooting and Known Issues

#### PHPCS (Code Sniffer)

The `phpcs.xml.dist` file is preconfigured to save the cache in the `cache/phpcs` directory at the root of the project. If this directory does not exist, Code Sniffer cannot create it automatically, and you will need to create it manually.

To disable the cache, you can simply comment out or remove this line from the `phpcs.xml.dist` file.

```xml
<arg name="cache" value="cache/phpcs" />
```

If you prefer to choose a custom path that better suits your habits, you can simply modify it.

#### Errors When Running Commands from the Console

All commands defined in the `composer.json` file are prefixed with the variable `XDEBUG_MODE=off`. This prevents `xdebug` from producing an excessive amount of output if the configuration is set to `xdebug.mode=debug`or `xdebug.mode=debug,develop`. If you run commands that are not defined in the `composer.json` file, you can suppress these messages as follows:

```sh
XDEBUG_MODE=off php omega command_name options
```

## Official Documentation

The official documentation for Omega is available [here](https://omega-mvc.github.io)

## Contributing

If you'd like to contribute to the OmegaMVC Serializable Closure package, please follow our [contribution guidelines](CONTRIBUTING.md).

## License

This project is open-source software licensed under the [GNU General Public License v3.0](LICENSE).
