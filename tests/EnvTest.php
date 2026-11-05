<?php

declare(strict_types=1);

namespace AmunCode\Config\Tests;

use AmunCode\Config\Env;
use Closure;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $key;

    private bool $hadEnvValue;
    private mixed $originalEnvValue;

    private bool $hadServerValue;
    private mixed $originalServerValue;

    private string|false $originalProcessValue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = 'AMUNCODE_CONFIG_TEST_' . strtoupper(bin2hex(random_bytes(6)));

        $this->hadEnvValue = array_key_exists($this->key, $_ENV);
        $this->originalEnvValue = $_ENV[$this->key] ?? null;

        $this->hadServerValue = array_key_exists($this->key, $_SERVER);
        $this->originalServerValue = $_SERVER[$this->key] ?? null;

        $this->originalProcessValue = getenv($this->key);

        unset($_ENV[$this->key], $_SERVER[$this->key]);
        putenv($this->key);
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

        if ($this->originalProcessValue === false) {
            putenv($this->key);
        } else {
            putenv($this->key . '=' . $this->originalProcessValue);
        }

        parent::tearDown();
    }

    public function testEnvArrayHasHighestLookupPriority(): void
    {
        $_ENV[$this->key] = 'from-env';
        $_SERVER[$this->key] = 'from-server';
        putenv($this->key . '=from-getenv');

        self::assertSame('from-env', Env::get($this->key));
    }

    public function testServerArrayHasPriorityOverGetenv(): void
    {
        $_SERVER[$this->key] = 'from-server';
        putenv($this->key . '=from-getenv');

        self::assertSame('from-server', Env::get($this->key));
    }

    public function testGetenvIsUsedAsFallback(): void
    {
        putenv($this->key . '=from-getenv');

        self::assertSame('from-getenv', Env::get($this->key));
    }

    public function testReturnsDefaultWhenVariableIsMissing(): void
    {
        self::assertSame('fallback', Env::get($this->key, 'fallback'));
    }

    public function testClosureDefaultIsEvaluatedOnlyWhenVariableIsMissing(): void
    {
        $calls = 0;
        $default = static function () use (&$calls): string {
            $calls++;

            return 'lazy-fallback';
        };

        self::assertSame('lazy-fallback', Env::get($this->key, $default));
        self::assertSame(1, $calls);

        $_ENV[$this->key] = 'present';

        self::assertSame('present', Env::get($this->key, $default));
        self::assertSame(1, $calls);
    }

    public function testPreservesExistingEmptyStringInsteadOfUsingDefault(): void
    {
        $_ENV[$this->key] = '';

        self::assertSame('', Env::get($this->key, 'fallback'));
    }

    public function testPreservesZeroString(): void
    {
        $_ENV[$this->key] = '0';

        self::assertSame('0', Env::get($this->key, 'fallback'));
    }

    public function testNormalizesBooleanAndNullValues(): void
    {
        $cases = [
            'true' => true,
            '(true)' => true,
            'TRUE' => true,
            'false' => false,
            '(false)' => false,
            'FALSE' => false,
            'null' => null,
            '(null)' => null,
            'empty' => '',
            '(empty)' => '',
        ];

        foreach ($cases as $input => $expected) {
            $_ENV[$this->key] = $input;

            self::assertSame(
                $expected,
                Env::get($this->key),
                sprintf('Unexpected normalization for "%s".', $input)
            );
        }
    }

    public function testNormalizationTrimsForTokenMatchingButPreservesOrdinaryStrings(): void
    {
        $_ENV[$this->key] = '  true  ';
        self::assertTrue(Env::get($this->key));

        $_ENV[$this->key] = '  ordinary value  ';
        self::assertSame('  ordinary value  ', Env::get($this->key));
    }

    public function testNonStringValuesArePreserved(): void
    {
        $_ENV[$this->key] = 123;

        self::assertSame(123, Env::get($this->key));
    }

    public function testHasReturnsTrueForEnvServerAndGetenvValues(): void
    {
        $_ENV[$this->key] = 'from-env';
        self::assertTrue(Env::has($this->key));

        unset($_ENV[$this->key]);
        $_SERVER[$this->key] = 'from-server';
        self::assertTrue(Env::has($this->key));

        unset($_SERVER[$this->key]);
        putenv($this->key . '=from-getenv');
        self::assertTrue(Env::has($this->key));
    }

    public function testHasReturnsFalseWhenVariableIsMissing(): void
    {
        self::assertFalse(Env::has($this->key));
    }

    public function testAnExistingNullValueIsNotReplacedByDefault(): void
    {
        $_ENV[$this->key] = null;

        self::assertNull(Env::get($this->key, 'fallback'));
        self::assertTrue(Env::has($this->key));
    }
}
