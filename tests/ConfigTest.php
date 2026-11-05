<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests;

use AmunCode\Config\Config;
use AmunCode\Config\Tests\Support\TemporaryDirectoryTestCase;
use InvalidArgumentException;

final class ConfigTest extends TemporaryDirectoryTestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = $this->createDirectory('config');

        $this->writeFile(
            'config/app.php',
            "<?php\nreturn " . var_export([
                'name' => 'AmunCode',
                'debug' => false,
                'nullable' => null,
                'database' => [
                    'host' => 'localhost',
                    'port' => 3306,
                ],
                'features' => ['one', 'two'],
            ], true) . ";\n"
        );
    }

    public function testGetWithoutKeyReturnsAllConfiguration(): void
    {
        $config = new Config($this->configPath);

        self::assertSame(
            [
                'app' => [
                    'name' => 'AmunCode',
                    'debug' => false,
                    'nullable' => null,
                    'database' => [
                        'host' => 'localhost',
                        'port' => 3306,
                    ],
                    'features' => ['one', 'two'],
                ],
            ],
            $config->get()
        );
    }

    public function testGetReturnsExistingTopLevelValue(): void
    {
        $config = new Config($this->configPath);

        self::assertSame('AmunCode', $config->get('app')['name']);
    }

    public function testGetSupportsDotNotation(): void
    {
        $config = new Config($this->configPath);

        self::assertSame('localhost', $config->get('app.database.host'));
        self::assertSame(3306, $config->get('app.database.port'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = new Config($this->configPath);

        self::assertSame('fallback', $config->get('app.missing', 'fallback'));
    }

    public function testGetDoesNotReplaceAnExistingNullValueWithDefault(): void
    {
        $config = new Config($this->configPath);

        self::assertNull($config->get('app.nullable', 'fallback'));
    }

    public function testGetReturnsDefaultForEmptyOrMalformedKey(): void
    {
        $config = new Config($this->configPath);

        self::assertSame('fallback', $config->get('', 'fallback'));
        self::assertSame('fallback', $config->get('app..name', 'fallback'));
        self::assertSame('fallback', $config->get('.app', 'fallback'));
        self::assertSame('fallback', $config->get('app.', 'fallback'));
    }

    public function testGetTrimsOuterWhitespaceFromKey(): void
    {
        $config = new Config($this->configPath);

        self::assertSame('AmunCode', $config->get('  app.name  '));
    }

    public function testSetUpdatesExistingValue(): void
    {
        $config = new Config($this->configPath);
        $config->set('app.name', 'NewName');

        self::assertSame('NewName', $config->get('app.name'));
    }

    public function testSetCreatesMissingNestedArrays(): void
    {
        $config = new Config($this->configPath);
        $config->set('services.mail.host', 'mail.example.test');

        self::assertSame(
            'mail.example.test',
            $config->get('services.mail.host')
        );
    }

    public function testSetCanReplaceAnIntermediateScalarWithAnArray(): void
    {
        $config = new Config($this->configPath);
        $config->set('app.database', 'disabled');
        $config->set('app.database.host', 'db.example.test');

        self::assertSame('db.example.test', $config->get('app.database.host'));
    }

    public function testSetMergesAnArrayRecursively(): void
    {
        $config = new Config($this->configPath);
        $config->set([
            'app' => [
                'debug' => true,
                'database' => ['host' => 'db.example.test'],
            ],
            'cache' => ['enabled' => true],
        ]);

        self::assertTrue($config->get('app.debug'));
        self::assertSame('db.example.test', $config->get('app.database.host'));
        self::assertSame(3306, $config->get('app.database.port'));
        self::assertTrue($config->get('cache.enabled'));
    }

    public function testSetReplacesArrayValueUsingDotNotation(): void
    {
        $config = new Config($this->configPath);
        $config->set('app.features', ['replacement']);

        self::assertSame(
            ['replacement'],
            $config->get('app.features')
        );
    }

    public function testArrayMergeUsesRecursiveReplacementByNumericIndex(): void
    {
        $config = new Config($this->configPath);

        $config->set([
            'app' => [
                'features' => ['replacement'],
            ],
        ]);

        self::assertSame(
            ['replacement', 'two'],
            $config->get('app.features')
        );
    }

    public function testSetRejectsEmptyOrMalformedKeys(): void
    {
        $config = new Config($this->configPath);

        foreach (['', ' ', '.app', 'app.', 'app..name'] as $key) {
            try {
                $config->set($key, 'value');
                self::fail(sprintf('Expected key "%s" to be rejected.', $key));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
