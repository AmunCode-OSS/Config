<?php

declare(strict_types=1);

namespace AmunCode\Config;

use InvalidArgumentException;

final class Config
{
    /**
     * Loaded configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(string $configPath)
    {
        $this->config = (new ConfigurationLoader($configPath))
            ->load();
    }

    /**
     * Retrieve all configuration or a value using dot notation.
     */
    public function get(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $this->config;
        }

        $key = trim($key);
        $segments = explode('.', $key);

        if ($key === '' || in_array('', $segments, true)) {
            return $default;
        }

        $value = $this->config;

        foreach ($segments as $segment) {
            if (
                !is_array($value)
                || !array_key_exists($segment, $value)
            ) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation,
     * or merge an array of configuration values.
     *
     * Array input uses array_replace_recursive() semantics.
     *
     * @param string|array<string, mixed> $key
     */
    public function set(
        string|array $key,
        mixed $value = null
    ): void {
        if (is_array($key)) {
            $this->config = array_replace_recursive(
                $this->config,
                $key
            );

            return;
        }

        $key = trim($key);
        $segments = explode('.', $key);

        if ($key === '' || in_array('', $segments, true)) {
            throw new InvalidArgumentException(
                'Configuration key cannot be empty or contain empty segments.'
            );
        }

        $lastSegment = array_pop($segments);
        $current = &$this->config;

        foreach ($segments as $segment) {
            if (
                !isset($current[$segment])
                || !is_array($current[$segment])
            ) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[$lastSegment] = $value;

        unset($current);
    }
}
