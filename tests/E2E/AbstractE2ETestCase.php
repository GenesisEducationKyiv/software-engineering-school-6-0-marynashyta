<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use Playwright\Testing\PlaywrightTestCaseTrait;

abstract class AbstractE2ETestCase extends TestCase
{
    use PlaywrightTestCaseTrait;

    protected string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = $this->envString('APP_URL', 'http://localhost:8080');
        $this->setUpPlaywright();
    }

    protected function tearDown(): void
    {
        if ($this->status()->isFailure() || $this->status()->isError()) {
            $name = str_replace(['\\', '::', ' '], '_', $this->nameWithDataSet());
            $path = __DIR__ . '/screenshots/' . $name . '.png';
            @mkdir(dirname($path), 0755, true);
            $this->page->screenshot($path);
        }

        $this->tearDownPlaywright();
        parent::tearDown();
    }

    protected function visit(string $path): void
    {
        $this->page->goto($this->baseUrl . $path);
    }

    /** Returns a unique email address for each test run to avoid duplicate-subscription conflicts. */
    protected function uniqueEmail(string $prefix = 'e2e'): string
    {
        return $prefix . '+' . bin2hex(random_bytes(6)) . '@example.com';
    }

    private function envString(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}
