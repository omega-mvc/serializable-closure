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

The Serializable Closure package provides a convenient and secure way to serialize closures in PHP. It allows you to serialize and unserialize closures, preserving their state and functionality even across different PHP processes. This can be particularly useful in scenarios where closures need to be stored and retrieved, such as in caching mechanisms or queue systems.

## How it Works

The package introduces two main classes: `SerializableClosure` and `UnsignedSerializableClosure`.

- **`SerializableClosure`**: This class is designed for closures that require additional security measures. It supports signed serialization, which means the closure is associated with a secret key for added security. The signer used is configurable through the `setSecretKey` method.

- **`UnsignedSerializableClosure`**: This class is suitable for closures that don't require a secret key for signing. It provides a straightforward way to serialize closures without additional security measures.

### ⚠️ Experimental Feature: Serialization of Anonymous Functions

**Caution: This feature is experimental!** We've added support for the serialization of anonymous functions, but it comes with a warning. This feature is considered experimental, and we recommend using it only if you fully understand its implications.

Anonymous function serialization involves intricacies and potential risks, and its usage should be approached with caution. If you're unsure about the consequences or don't specifically need this functionality, it's advisable to stick to serializing named functions or closures.

Before incorporating this feature into your code, ensure you are aware of the implications and are comfortable handling any potential issues that might arise. Proceed with caution!

## Requirements

* PHP 8.2 or later

## Installation via Composer

To install the package, add the following to your `composer.json` file:

```json
{
    "require": {
        "omega-mvc/serializable-closure": "^1.0.0"
    }
}
```

Alternatively, you can simply run the following from the command line:

```sh
composer require omega-mvc/serializable-closure "^1.0.0"
```

If you want to include the test sources, use

```sh
composer require --prefer-source omega-mvc/serializable-closure "^1.0.0"
```

Then run:

```sh
composer install
```

## Getting Started

Example 1: Using `SerializableClosure` with `Signing`.

```php
use Omega\SerializableClosure\SerializableClosure;

// Create a closure.
$closure = fn() => 'YOUR_STRING_HERE';

// Set a secret key for signing.
SerializableClosure::setSecretKey('secret');

// Serialize the closure
$serialized = serialize(new SerializableClosure($closure));

// Unserialize and get the closure.
$closure = unserialize($serialized)->getClosure();

// Print result.
echo $closure(); // Output: YOUR_STRING_HERE
```

Example 2: Using `UnsignedSerializableClosure`.

```php
use Omega\SerializableClosure\UnsignedSerializableClosure;

// Create a closure
$closure = fn($value) => strtoupper($value);

// Serialize the closure
$serialized = serialize(new UnsignedSerializableClosure($closure));

// Unserialize and get the closure
$unserialized = unserialize($serialized)->getClosure();

// Invoke the closure
echo $unserialized('hello'); // Output: HELLO
```

Example 3: Using `SerializableClosure` with `Signing` and `anonymous functions`.

```php
use Omega\SerializableClosure\SerializableClosure;

// Create a closure.
$closure = function() {
    $anonymousClass = new class {
        public function getMessage() : string {
            return "Helloo from anonymous class!";
        }
    };
    
    return $anonymousClass->getMessage();
};

// Serialize
$serialized = serialize(new SerializableClosure($closure));

// Unserialize
$unserializedClosure = unserialize($serialized);

//Invoke the closure
$result = $unserializedClosure();

echo $result; // Output: Helloo from anonymous class!
```

Example 4: Using `UnsignedSerializableClosure` and `anonymous functions`.

```php
use Omega\SerializableClosure\UnsignedSerializableClosure;

// Create a closure
$anonymousFunction = function($name) {
    return "Hello, $name!";
};

// Create UnsignedSerializableClosure
$unsignedClosure = new UnsignedSerializableClosure($anonymousFunction);

 // Serialize
 $serialized = serialize($unsignedClosure);
 
 // Deserialize
 $unserialized = unserialize($serialized);
 
 // Invoke the closure
 $result = $unserialized("Jhon");
 
 // Echo the closure
 echo $result; // Output: Hello, Jhon!
```

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
