<?php

declare(strict_types=1);

namespace AmunCode\Config;

use Dotenv\Dotenv;
use InvalidArgumentException;
use LogicException;

final class EnvironmentLoader
{
    private bool $loaded = false;

    private ?string $loadedPath = null;

    /**
     * Load environment variables from a .env file.
     *
     * @param string $path         Directory containing the .env file.
     * @param bool   $requiredFile Whether the .env file must exist.
     */
    public function load(
        string $path,
        bool $requiredFile = false
    ): void {
        $resolvedPath = realpath($path);

        if ($resolvedPath === false || !is_dir($resolvedPath)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Environment directory "%s" does not exist.',
                    $path
                )
            );
        }

        if ($this->loaded) {
            if ($this->loadedPath !== $resolvedPath) {
                throw new LogicException(
                    'Environment variables have already been loaded from another directory.'
                );
            }

            return;
        }

        $dotenv = Dotenv::createImmutable($resolvedPath);

        if ($requiredFile) {
            $dotenv->load();
        } else {
            $dotenv->safeLoad();
        }

        $this->loaded = true;
        $this->loadedPath = $resolvedPath;
    }

    /**
     * Determine whether this instance completed environment loading.
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }
}
