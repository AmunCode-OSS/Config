<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class TemporaryDirectoryTestCase extends TestCase
{
    protected string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'amuncode-config-'
            . bin2hex(random_bytes(8));

        if (!mkdir($this->temporaryDirectory, 0700, true) && !is_dir($this->temporaryDirectory)) {
            throw new RuntimeException(
                sprintf('Unable to create temporary directory "%s".', $this->temporaryDirectory)
            );
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory) && is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    protected function createDirectory(string $relativePath): string
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . $relativePath;

        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException(
                sprintf('Unable to create directory "%s".', $path)
            );
        }

        return $path;
    }

    protected function writeFile(string $relativePath, string $contents): string
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create directory "%s".', $directory)
            );
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                sprintf('Unable to write file "%s".', $path)
            );
        }

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
