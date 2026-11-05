<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests\Integration;

use AmunCode\Config\Config;
use AmunCode\Config\Env;
use AmunCode\Config\EnvironmentLoader;
use AmunCode\Config\Tests\Support\TemporaryDirectoryTestCase;

final class ConfigurationIntegrationTest extends TemporaryDirectoryTestCase
{
    private string $key;

    private bool $hadEnvValue;
    private mixed $originalEnvValue;

    private bool $hadServerValue;
    private mixed $originalServerValue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = 'AMUNCODE_CONFIG_INTEGRATION_' . strtoupper(bin2hex(random_bytes(6)));

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

    public function testEnvironmentIsLoadedBeforePhpConfigurationFiles(): void
    {
        $this->writeFile('.env', $this->key . "=TestApplication\n");

        $configPath = $this->createDirectory('config');
        $this->writeFile(
            'config/app.php',
            "<?php\nreturn ['name' => \\AmunCode\\Config\\Env::get('{$this->key}')];\n"
        );

        $environment = new EnvironmentLoader();
        $environment->load($this->temporaryDirectory);

        $config = new Config($configPath);

        self::assertTrue($environment->isLoaded());
        self::assertSame('TestApplication', $config->get('app.name'));
    }

    public function testConfigCanLoadEvenWhenEnvironmentLoaderIsNotUsed(): void
    {
        $configPath = $this->createDirectory('config-without-env');
        $this->writeFile(
            'config-without-env/app.php',
            "<?php\nreturn ['name' => 'Standalone'];\n"
        );

        $config = new Config($configPath);

        self::assertSame('Standalone', $config->get('app.name'));
    }
}
