# AmunCode Config

A lightweight configuration and environment-loading library for PHP applications.

AmunCode Config provides a small set of focused classes for loading environment variables from a `.env` file, reading environment values, loading PHP configuration files, and accessing configuration values with dot notation.

## Requirements

- PHP `>= 8.4.1`
- [Symfony Finder](https://symfony.com/doc/current/components/finder.html) `^8.1`
- [PHP dotenv](https://github.com/vlucas/phpdotenv) `^5.8`

## Installation

Install the package with Composer:

```bash
composer require amuncode/config
```

Composer installs the required dependencies and configures the `AmunCode\Config\` namespace through PSR-4 autoloading.

## Features

- Load `.env` files optionally or require them to exist.
- Load environment variables without overwriting variables that are already present.
- Read environment values from `$_ENV`, `$_SERVER`, and `getenv()`.
- Normalize Laravel-style values such as `true`, `false`, `null`, and `empty`.
- Load top-level PHP configuration files from a directory.
- Retrieve and update configuration values using dot notation.
- Recursively merge configuration arrays.

## Quick start

Load the environment before loading PHP configuration files. This allows configuration files to read environment values safely.

The following example assumes the script is located in the project's `public/` directory:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Config;
use AmunCode\Config\EnvironmentLoader;

$root = dirname(__DIR__);

// Load .env if it exists.
$environment = new EnvironmentLoader();
$environment->load($root);

// Load PHP configuration files from the config directory.
$config = new Config($root . '/config');

$appName = $config->get('app.name', 'My Application');
$debug = $config->get('app.debug', false);
```

`EnvironmentLoader` and `Config` have separate responsibilities. `EnvironmentLoader` loads environment variables; `Config` loads PHP configuration files. The `Config` constructor does not load `.env` files.

## Environment file

AmunCode Config uses [PHP dotenv](https://github.com/vlucas/phpdotenv) to load environment variables from a `.env` file.

Create a `.env` file in your project root:

```dotenv
APP_NAME="My Application"
APP_DEBUG=true
APP_ENV=development

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=
```

For other developers, provide an `.env.example` file with placeholder values. Do not commit your actual `.env` file or real credentials.

### Loading the environment

Use `EnvironmentLoader` to load the environment variables:

```php
use AmunCode\Config\EnvironmentLoader;

$environment = new EnvironmentLoader();

// Load .env if it exists.
$environment->load($root);
```

By default, the `.env` file is optional. To require it:

```php
$environment->load($root, requiredFile: true);
```

When the file is required but does not exist, an exception is thrown.

### Initialization order

Environment variables must be loaded before PHP configuration files are initialized, especially when those files use `Env::get()`.

The recommended initialization order is:

1. Load the Composer autoloader.
2. Load environment variables using `EnvironmentLoader`.
3. Initialize `Config` to load PHP configuration files.
4. Access configuration values.

For example, consider this project structure:

```text
project/
├── .env
├── config/
│   └── app.php
├── public/
│   └── index.php
└── vendor/
```

Create `config/app.php`:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;

return [
    'name' => Env::get('APP_NAME', 'My Application'),
    'debug' => Env::get('APP_DEBUG', false),
    'environment' => Env::get('APP_ENV', 'production'),
];
```

Then initialize everything in `public/index.php`:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Config;
use AmunCode\Config\EnvironmentLoader;

$root = dirname(__DIR__);

// Load Composer autoloader.
require $root . '/vendor/autoload.php';

// Load environment variables first.
$environment = new EnvironmentLoader();
$environment->load($root);

// Load PHP configuration files.
$config = new Config($root . '/config');

// Access configuration values.
$appName = $config->get('app.name');
$debug = $config->get('app.debug');
```

The `Config` class does not automatically load the `.env` file. Environment initialization is an explicit step handled by `EnvironmentLoader`. Following this order ensures that environment variables are available when PHP configuration files are loaded.

## Environment loading

### `EnvironmentLoader`

`EnvironmentLoader` loads a `.env` file from a directory using `vlucas/phpdotenv`.

```php
use AmunCode\Config\EnvironmentLoader;

$environment = new EnvironmentLoader();

// Optional: no exception is thrown if the .env file is absent.
$environment->load('/path/to/project');

// Required: throws if the .env file is absent or cannot be loaded.
$environment->load('/path/to/project', requiredFile: true);

// Check whether this instance completed loading.
$loaded = $environment->isLoaded();
```

The `load()` method accepts:

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `$path` | `string` | — | Directory containing the `.env` file. |
| `$requiredFile` | `bool` | `false` | When `true`, the `.env` file is required. When `false`, a missing file is allowed. |

A single `EnvironmentLoader` instance loads at most once. Calling `load()` again with the same resolved directory does nothing. Calling it with a different directory after a successful load throws a `LogicException`. The `isLoaded()` method reports the state of that instance; it does not report whether another instance or another part of the process loaded environment variables.

The loader uses Dotenv's immutable repository. Existing environment values are not overwritten by values from the `.env` file. It does not use `putenv()`.

### Reading environment values with `Env`

Use `Env::get()` to retrieve an environment value:

```php
use AmunCode\Config\Env;

$appName = Env::get('APP_NAME', 'AmunCode');
$debug = Env::get('APP_DEBUG', false);
$databaseUrl = Env::get('DATABASE_URL');
```

Environment lookup follows this order:

1. `$_ENV`
2. `$_SERVER`
3. `getenv()`

The first source containing the key takes precedence. The default is returned only when the variable is missing. An existing empty string or `null` value is preserved.

`Env::get()` accepts a lazy default using a closure. The closure is evaluated only when the variable is missing:

```php
$databaseName = Env::get(
    'DB_DATABASE',
    static fn (): string => 'application_default'
);
```

Use `Env::has()` to check whether a variable exists in any supported source:

```php
if (Env::has('APP_KEY')) {
    // APP_KEY exists, even if its value is an empty string or null.
}
```

#### Value normalization

String values are normalized case-insensitively for the following representations:

| Environment value | Result |
| --- | --- |
| `true` or `(true)` | `true` |
| `false` or `(false)` | `false` |
| `null` or `(null)` | `null` |
| `empty` or `(empty)` | `''` |
| `0` | `'0'` |
| An empty value | `''` |

Other string values are returned unchanged. Non-string values are also returned unchanged.

## PHP configuration files

`Config` loads PHP files directly inside the configuration directory. Each file must return an array. The filename, without the `.php` extension, becomes the top-level configuration key.

For example:

```text
project/
├── config/
│   ├── app.php
│   ├── database.php
│   └── services.php
└── public/
    └── index.php
```

`config/app.php`:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;

return [
    'name' => Env::get('APP_NAME', 'AmunCode'),
    'debug' => Env::get('APP_DEBUG', false),
];
```

`config/database.php`:

```php
<?php

declare(strict_types=1);

use AmunCode\Config\Env;

return [
    'driver' => Env::get('DB_CONNECTION', 'mysql'),
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE'),
    'username' => Env::get('DB_USERNAME'),
    'password' => Env::get('DB_PASSWORD'),
];
```

After loading the environment, initialize the configuration:

```php
$environment = new EnvironmentLoader();
$environment->load($root);

$config = new Config($root . '/config');

$config->get('app.name');       // AmunCode
$config->get('database.host');  // 127.0.0.1
```

Files are loaded in filename order. Files in subdirectories are not loaded. A configuration file that does not return an array causes an `UnexpectedValueException`. A missing configuration directory causes an `InvalidArgumentException`.

## Working with configuration

### Retrieving values

Use dot notation to retrieve nested values:

```php
$config->get('app.name');
$config->get('database.host');
```

Pass a default for missing or invalid keys:

```php
$config->get('cache.default', 'file');
```

Call `get()` without a key to retrieve the complete configuration array:

```php
$all = $config->get();
```

An existing key with a `null` value is different from a missing key. In that case, `get()` returns `null`, not the supplied default.

### Setting a value

Pass a string key to set or replace a value. Dot notation creates intermediate arrays when necessary:

```php
$config->set('app.name', 'My Application');
$config->set('database.options.timeout', 10);
```

### Merging configuration arrays

Pass an array to `set()` to merge values into the existing configuration:

```php
$config->set([
    'app' => [
        'debug' => true,
        'timezone' => 'Africa/Cairo',
    ],
]);
```

Array input uses PHP's `array_replace_recursive()` behavior. Existing string keys are replaced recursively. Numeric keys are replaced by index; this operation does not append numeric values.

## Using a global `env()` helper (optional)

The package exposes `Env::get()` and does not define a global `env()` function automatically. Applications that prefer a global helper can define one in their own support file:

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

This keeps the package independent of an application's global helper conventions.

## Testing

Install the development dependencies:

```bash
composer install
```

Run the PHPUnit suite:

```bash
vendor/bin/phpunit
```

The tests cover configuration retrieval and mutation, PHP configuration file loading, environment lookup and normalization, optional and required `.env` loading, and the environment/configuration loading lifecycle.

## License

AmunCode Config is open-sourced software licensed under the MIT License.
