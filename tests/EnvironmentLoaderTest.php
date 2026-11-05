<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests;

use AmunCode\Config\EnvironmentLoader;
use AmunCode\Config\Tests\Support\TemporaryDirectoryTestCase;
use Dotenv\Exception\InvalidPathException;
use InvalidArgumentException;
use LogicException;

final class EnvironmentLoaderTest extends TemporaryDirectoryTestCase
{
    private string $key;

    private bool $hadEnvValue;
    private mixed $originalEnvValue;

    private bool $hadServerValue;
    private mixed $originalServerValue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = 'AMUNCODE_CONFIG_TEST_' . strtoupper(bin2hex(random_bytes(6)));

        $this->hadEnvValue = array_key_exists($this->key, $_ENV);
        $this->originalEnvValue = $_ENV[$this->key] ?? null;

        $this->hadServerValue = array_key_exists($this->key, $_SERVER);
        $this->originalServerValue = $_SERVER[$this->key] ?? null;

        unset($_ENV[$this->key], $_SERVER[$this->key]);
    }

    protected function tearDown(): void
    {
        if ($this->hadEnvValue) {
            $_ENV[$this->key] = $this->originalEnvValue;
        } else {
            unset($_ENV[$this->key]);
        }

        if ($this->hadServerValue) {
            $_SERVER[$this->key] = $this->originalServerValue;
        } else {
            unset($_SERVER[$this->key]);
        }

        parent::tearDown();
    }

    public function testIsLoadedIsFalseBeforeLoading(): void
    {
        $loader = new EnvironmentLoader();

        self::assertFalse($loader->isLoaded());
    }

    public function testLoadsAnOptionalEnvironmentFile(): void
    {
        $this->writeFile('.env', $this->key . "=loaded-value\n");
        $loader = new EnvironmentLoader();

        $loader->load($this->temporaryDirectory);

        self::assertTrue($loader->isLoaded());
        self::assertSame('loaded-value', $_ENV[$this->key] ?? null);
    }

    public function testOptionalMissingFileDoesNotThrowAndMarksLoaderAsLoaded(): void
    {
        $loader = new EnvironmentLoader();

        $loader->load($this->temporaryDirectory);

        self::assertTrue($loader->isLoaded());
    }

    public function testRequiredEnvironmentFileIsLoaded(): void
    {
        $this->writeFile('.env', $this->key . "=required-value\n");
        $loader = new EnvironmentLoader();

        $loader->load($this->temporaryDirectory, requiredFile: true);

        self::assertTrue($loader->isLoaded());
        self::assertSame('required-value', $_ENV[$this->key] ?? null);
    }

    public function testRequiredMissingFileThrowsAndDoesNotMarkLoaderAsLoaded(): void
    {
        $loader = new EnvironmentLoader();

        try {
            $loader->load($this->temporaryDirectory, requiredFile: true);
            self::fail('Expected a missing required .env file to throw.');
        } catch (InvalidPathException) {
            self::assertFalse($loader->isLoaded());
        }
    }

    public function testRejectsNonexistentDirectory(): void
    {
        $loader = new EnvironmentLoader();

        $this->expectException(InvalidArgumentException::class);
        $loader->load($this->temporaryDirectory . '/does-not-exist');
    }

    public function testDoesNotOverwriteAnExistingEnvironmentValue(): void
    {
        $_ENV[$this->key] = 'preexisting-value';
        $_SERVER[$this->key] = 'preexisting-value';

        $this->writeFile('.env', $this->key . "=file-value\n");
        $loader = new EnvironmentLoader();
        $loader->load($this->temporaryDirectory);

        self::assertSame('preexisting-value', $_ENV[$this->key]);
    }

    public function testLoadingTwiceFromTheSameDirectoryIsIdempotent(): void
    {
        $this->writeFile('.env', $this->key . "=first-value\n");
        $loader = new EnvironmentLoader();
        $loader->load($this->temporaryDirectory);

        $this->writeFile('.env', $this->key . "=second-value\n");
        $loader->load($this->temporaryDirectory);

        self::assertSame('first-value', $_ENV[$this->key] ?? null);
        self::assertTrue($loader->isLoaded());
    }

    public function testCannotReuseLoadedInstanceForAnotherDirectory(): void
    {
        $this->writeFile('.env', $this->key . "=first-value\n");
        $loader = new EnvironmentLoader();
        $loader->load($this->temporaryDirectory);

        $anotherDirectory = $this->createDirectory('another-project');

        $this->expectException(LogicException::class);
        $loader->load($anotherDirectory);
    }
}
