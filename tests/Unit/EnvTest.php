<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Env;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private const KEY = 'PHPUNIT_ENV_TEST_VAR';

    protected function tearDown(): void
    {
        unset($_ENV[self::KEY]);
        parent::tearDown();
    }

    // ── Env::string() ─────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsDefaultStringWhenKeyIsAbsent(): void
    {
        unset($_ENV[self::KEY]);

        self::assertSame('fallback', Env::string(self::KEY, 'fallback'));
    }

    #[Test]
    public function itReturnsDefaultStringWhenValueIsEmptyString(): void
    {
        $_ENV[self::KEY] = '';

        self::assertSame('fallback', Env::string(self::KEY, 'fallback'));
    }

    #[Test]
    public function itReturnsDefaultStringWhenValueIsNotAString(): void
    {
        $_ENV[self::KEY] = 123;

        self::assertSame('fallback', Env::string(self::KEY, 'fallback'));
    }

    #[Test]
    public function itReturnsValueWhenStringIsSet(): void
    {
        $_ENV[self::KEY] = 'hello';

        self::assertSame('hello', Env::string(self::KEY, 'fallback'));
    }

    // ── Env::int() ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsDefaultIntWhenKeyIsAbsent(): void
    {
        unset($_ENV[self::KEY]);

        self::assertSame(99, Env::int(self::KEY, 99));
    }

    #[Test]
    public function itReturnsDefaultIntWhenValueIsNonNumeric(): void
    {
        $_ENV[self::KEY] = 'not-a-number';

        self::assertSame(99, Env::int(self::KEY, 99));
    }

    #[Test]
    public function itParsesIntegerString(): void
    {
        $_ENV[self::KEY] = '42';

        self::assertSame(42, Env::int(self::KEY));
    }

    #[Test]
    public function itTruncatesFloatStringToInt(): void
    {
        $_ENV[self::KEY] = '3.9';

        self::assertSame(3, Env::int(self::KEY));
    }

    #[Test]
    public function itReturnsExplicitDefaultForInt(): void
    {
        unset($_ENV[self::KEY]);

        self::assertSame(0, Env::int(self::KEY));
    }

    // ── Env::bool() ───────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsFalseWhenBoolKeyIsAbsent(): void
    {
        unset($_ENV[self::KEY]);

        self::assertFalse(Env::bool(self::KEY));
    }

    #[Test]
    public function itReturnsFalseWhenValueIsNotAString(): void
    {
        $_ENV[self::KEY] = 1;

        self::assertFalse(Env::bool(self::KEY));
    }

    #[Test]
    #[DataProvider('trueBoolValues')]
    public function itReturnsTrueForTruthyString(string $value): void
    {
        $_ENV[self::KEY] = $value;

        self::assertTrue(Env::bool(self::KEY));
    }

    #[Test]
    #[DataProvider('falseBoolValues')]
    public function itReturnsFalseForFalsyString(string $value): void
    {
        $_ENV[self::KEY] = $value;

        self::assertFalse(Env::bool(self::KEY));
    }

    /** @return array<string, array{string}> */
    public static function trueBoolValues(): array
    {
        return [
            'lowercase true' => ['true'],
            'uppercase TRUE' => ['TRUE'],
            'mixed True'     => ['True'],
            'one'            => ['1'],
            'lowercase yes'  => ['yes'],
            'uppercase YES'  => ['YES'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function falseBoolValues(): array
    {
        return [
            'false string'  => ['false'],
            'zero string'   => ['0'],
            'no string'     => ['no'],
            'empty string'  => [''],
            'random text'   => ['maybe'],
        ];
    }
}
