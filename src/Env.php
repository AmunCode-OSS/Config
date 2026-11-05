<?php

declare(strict_types=1);

namespace AmunCode\Config;

use Closure;

final class Env
{
    /**
     * Retrieve an environment variable.
     *
     * Lookup priority:
     * 1. $_ENV
     * 2. $_SERVER
     * 3. getenv()
     *
     * The default is used only when the variable is missing.
     * Empty strings and null values are preserved.
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        } elseif (array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        } else {
            $value = getenv($key);

            if ($value === false) {
                return self::resolveDefault($default);
            }
        }

        return self::normalize($value);
    }

    /**
     * Determine whether an environment variable exists.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }

    /**
     * Resolve a default value, including lazy closures.
     */
    private static function resolveDefault(mixed $default): mixed
    {
        return $default instanceof Closure
            ? $default()
            : $default;
    }

    /**
     * Normalize Laravel-style environment values.
     */
    private static function normalize(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)'   => null,
            default            => $value,
        };
    }
}
