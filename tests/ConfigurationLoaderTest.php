<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests;

use AmunCode\Config\ConfigurationLoader;
use AmunCode\Config\Tests\Support\TemporaryDirectoryTestCase;
use InvalidArgumentException;
use UnexpectedValueException;

final class ConfigurationLoaderTest extends TemporaryDirectoryTestCase
{
    public function testLoadsPhpConfigurationFilesByFilename(): void
    {
        $configPath = $this->createDirectory('config');

        $this->writeFile(
            'config/z.php',
            "<?php return ['value' => 'z'];\n"
        );

        $this->writeFile(
            'config/a.php',
            "<?php return ['value' => 'a'];\n"
        );

        $loader = new ConfigurationLoader($configPath);

        self::assertSame(
            [
                'a' => ['value' => 'a'],
                'z' => ['value' => 'z'],
            ],
            $loader->load()
        );
    }

    public function testLoadsOnlyFilesDirectlyInsideConfigurationDirectory(): void
    {
        $configPath = $this->createDirectory('config');

        $this->writeFile(
            'config/app.php',
            "<?php return ['name' => 'AmunCode'];\n"
        );

        $this->writeFile(
            'config/nested/ignored.php',
            "<?php return ['ignored' => true];\n"
        );

        $this->writeFile('config/readme.txt', 'not a PHP configuration file');

        self::assertSame(
            ['app' => ['name' => 'AmunCode']],
            (new ConfigurationLoader($configPath))->load()
        );
    }

    public function testReturnsEmptyArrayWhenNoPhpFilesExist(): void
    {
        $configPath = $this->createDirectory('empty-config');

        self::assertSame([], (new ConfigurationLoader($configPath))->load());
    }

    public function testThrowsWhenConfigurationDirectoryDoesNotExist(): void
    {
        $loader = new ConfigurationLoader(
            $this->temporaryDirectory . '/missing-config'
        );

        $this->expectException(InvalidArgumentException::class);
        $loader->load();
    }

    public function testThrowsWhenConfigurationFileDoesNotReturnAnArray(): void
    {
        $configPath = $this->createDirectory('invalid-config');

        $this->writeFile(
            'invalid-config/app.php',
            "<?php return 'not-an-array';\n"
        );

        $this->expectException(UnexpectedValueException::class);
        (new ConfigurationLoader($configPath))->load();
    }
}
