<?php

declare(strict_types=1);

namespace AmunCode\Config;

use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use UnexpectedValueException;

final class ConfigurationLoader
{
    public function __construct(
        private readonly string $configPath
    ) {
    }

    /**
     * Load PHP configuration files from the configuration directory.
     *
     * Each file must return an array. Only files directly inside the
     * configuration directory are loaded.
     *
     * Example: config/database.php becomes $config['database'].
     *
     * @return array<string, array<array-key, mixed>>
     */
    public function load(): array
    {
        if (!is_dir($this->configPath)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configuration directory "%s" does not exist.',
                    $this->configPath
                )
            );
        }

        $files = (new Finder())
            ->files()
            ->name('*.php')
            ->depth('== 0')
            ->in($this->configPath)
            ->sortByName();

        $config = [];

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            $path = $file->getPathname();
            $value = require $path;

            if (!is_array($value)) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Configuration file "%s" must return an array.',
                        $path
                    )
                );
            }

            $config[$name] = $value;
        }

        return $config;
    }
}
