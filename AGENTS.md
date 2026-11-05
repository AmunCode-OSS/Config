# AmunCode Config — Application Integration Guide

These instructions are for AI coding agents and developers working on an application that **uses** `amuncode/config` as a dependency.

The goal is to integrate the package through its public API, follow its intended initialization order, and avoid duplicating functionality that the package already provides.

## 1. Package overview

`amuncode/config` provides configuration and environment-variable utilities for PHP applications.

Its main classes are:

| Class | Responsibility |
| --- | --- |
| `AmunCode\Config\EnvironmentLoader` | Loads environment variables from a `.env` file. |
| `AmunCode\Config\Env` | Reads and normalizes environment variables. |
| `AmunCode\Config\ConfigurationLoader` | Loads PHP configuration files from a directory. |
| `AmunCode\Config\Config` | Provides access to loaded configuration values. |

Use the package's public classes rather than implementing another environment loader or configuration repository in the application.

## 2. Requirements and installation

The package requires PHP `>=8.4.1` and has runtime dependencies on Symfony Finder and `vlucas/phpdotenv`.

Install it with Composer:

```bash
composer require amuncode/config
```

Import classes from the `AmunCode\Config` namespace:

```php
use AmunCode\Config\Config;
use AmunCode\Config\Env;
use AmunCode\Config\EnvironmentLoader;
```

Do not copy the package's source files into the application. Depend on the Composer package and use its public API.

## 3. Initialization order

Load the environment **once**, early in the application's entry point, before loading PHP configuration files or reading environment-dependent settings.

A typical front controller (`public/index.php`) looks like this:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;
use AmunCode\Config\EnvironmentLoader;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$environment = new EnvironmentLoader();
$environment->load($root);

$debug = filter_var(
    Env::get('APP_DEBUG', false),
    FILTER_VALIDATE_BOOL
);

$app = require $root . '/bootstrap/app.php';

$app->run();
```

Adapt the entry point and application bootstrap to the host application's architecture. The important order is:

1. Load Composer's autoloader.
2. Load the `.env` file once.
3. Read environment values needed during startup.
4. Load application configuration files.
5. Initialize the rest of the application.

Do not load `.env` again in `Config`, a service provider, or another bootstrap file. Keep and reuse the `EnvironmentLoader` instance when the application needs to inspect its loading state.

### Required or optional `.env`

By default, the `.env` file is optional:

```php
$environment->load($root);
```

Require the file explicitly when the application cannot start without it:

```php
$environment->load($root, requiredFile: true);
```

The `requiredFile` argument controls whether the file must exist. It does not validate individual variables inside the file.

`EnvironmentLoader::isLoaded()` reports whether **that instance** completed its loading operation. It is not a process-wide or static status shared by other instances.

The loader uses an immutable Dotenv repository. Existing environment values are not overwritten by values in `.env`. Do not introduce `putenv()` to work around this behavior.

## 4. Reading environment variables

Use `Env::get()` to retrieve an environment value:

```php
$databaseHost = Env::get('DB_HOST', '127.0.0.1');
$debug = Env::get('APP_DEBUG', false);
```

Lookup priority is:

1. `$_ENV`
2. `$_SERVER`
3. `getenv()`

The default is used only when the variable is missing. An existing empty string or `null` value is not treated as missing.

Examples of normalization:

| Environment value | Returned value |
| --- | --- |
| `true` or `(true)` | `true` |
| `false` or `(false)` | `false` |
| `null` or `(null)` | `null` |
| `empty` or `(empty)` | `''` |
| Empty value (`KEY=`) | `''` |
| `0` | `'0'` |
| Missing variable | The supplied default |

A closure can be supplied as a lazy default. It is evaluated only when the variable is missing:

```php
$secret = Env::get('APP_SECRET', static function (): string {
    return createApplicationSecret();
});
```

Use `Env::has()` to check whether a variable exists without retrieving its value:

```php
if (! Env::has('DB_PASSWORD')) {
    throw new RuntimeException('DB_PASSWORD is not configured.');
}
```

Do not use `empty()` to check environment values when `'0'` or `''` has meaningful semantics. Validate required values explicitly in the application. For example:

```php
$databaseHost = Env::get('DB_HOST');

if (!is_string($databaseHost) || $databaseHost === '') {
    throw new RuntimeException('DB_HOST must be configured.');
}
```

## 5. PHP configuration files

Store application configuration in PHP files in a configuration directory, commonly `config/`:

```text
config/
├── app.php
├── database.php
└── cache.php
```

Each file must return an array. The filename (without `.php`) becomes the top-level configuration key.

For example, `config/database.php`:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;

return [
    'driver' => Env::get('DB_CONNECTION', 'mysql'),
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', ''),
    'username' => Env::get('DB_USERNAME', ''),
    'password' => Env::get('DB_PASSWORD', ''),
];
```

Load the directory with `Config`:

```php
$config = new Config($root . '/config');
```

Only PHP files directly inside the supplied directory are loaded; nested directories are not scanned. Each file must return an array, otherwise loading fails with an exception. Ensure the environment is loaded before constructing `Config` when configuration files read environment variables.

## 6. Reading and changing configuration

Retrieve an individual value using dot notation:

```php
$appName = $config->get('app.name', 'My Application');
$databaseHost = $config->get('database.host');
```

Retrieve the complete configuration array:

```php
$allConfig = $config->get();
```

Set a value using dot notation:

```php
$config->set('app.name', 'Example Application');
$config->set('database.options.timeout', 10);
```

Passing an array merges values recursively using `array_replace_recursive()` semantics:

```php
$config->set([
    'database' => [
        'host' => 'db.internal',
        'options' => [
            'timeout' => 20,
        ],
    ],
]);
```

A string-key `set()` replaces the value at that key. Array input recursively replaces matching values; numeric array keys are replaced by index rather than appended. Do not assume that array input performs a list append or a deep merge that preserves every previous numeric element.

The `Config` instance holds configuration in memory. Changes made with `set()` are runtime changes; they do not write back to the PHP configuration files.

## 7. Optional global `env()` helper

The package exposes `Env::get()` as its API. It does not require applications to define a global `env()` function.

If the application wants Laravel-style global syntax, define a small application-level helper that delegates to the package:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}
```

Autoload that helper from the application's own Composer configuration if appropriate. Do not add a global helper to vendor code or modify the package just to provide an application-specific global function.

## 8. Error handling and validation

The package reports invalid setup or configuration data with exceptions. Applications should handle these at an appropriate bootstrap or request boundary and avoid exposing sensitive configuration values in error responses or logs.

Validate application-specific requirements explicitly. For example, the package can return an empty `DB_HOST` because an empty value is distinct from a missing value; the application decides whether that value is acceptable.

Do not assume that loading a `.env` file validates every required environment variable. Environment loading and application configuration validation are separate responsibilities.

## 9. Tests and development

When changing application code that uses this package:

- Add or update tests for the application's integration and configuration behavior.
- Do not duplicate the package's own unit tests in the application unless testing an application-specific contract.
- Run the application's test suite after changing bootstrap or configuration initialization.
- Use temporary directories and non-sensitive test values when testing `.env` loading.
- Restore modified `$_ENV` and `$_SERVER` values after tests to prevent cross-test contamination.

When changing the package itself, follow the package repository's own contributor instructions and run its PHPUnit suite.

## 10. Security practices

- Do not commit real `.env` files, credentials, API keys, or production secrets.
- Commit a `.env.example` containing placeholder values when useful.
- Do not expose `Config::get()` output, environment values, or exception details in public error responses.
- Do not write secrets into debug output or logs.
- Keep production error display disabled and use the application's logging and error-handling mechanisms.

## 11. Agent behavior when integrating the package

When asked to integrate or modify `amuncode/config` in an application:

1. Inspect the application's existing entry point, Composer autoload configuration, bootstrap flow, and configuration directory before changing files.
2. Reuse the existing `EnvironmentLoader` initialization if one is already present; do not load `.env` twice.
3. Use `Env` for environment-variable access and `Config` for application configuration access.
4. Preserve the application's existing dependency-injection container and bootstrap architecture.
5. Do not invent package methods or assume behavior that is not part of the public API described here. Inspect the installed package version when behavior or signatures are uncertain.
6. Make the smallest change that fits the application's architecture, and add tests for the integration behavior being changed.
