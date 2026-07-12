<?php

declare(strict_types=1);

namespace Tests\Unit\Consumer;

use NotificationService\Consumer\RetryPolicy;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    #[Test]
    public function invalidArgumentExceptionIsNotRetryable(): void
    {
        $policy = new RetryPolicy();

        $this->assertFalse($policy->isRetryable(new \InvalidArgumentException('bad payload')));
    }

    #[Test]
    public function jsonExceptionIsNotRetryable(): void
    {
        $policy = new RetryPolicy();

        $this->assertFalse($policy->isRetryable(new \JsonException('bad json')));
    }

    #[Test]
    public function transientExceptionIsRetryable(): void
    {
        $policy = new RetryPolicy();

        $this->assertTrue($policy->isRetryable(new PHPMailerException('SMTP timeout')));
    }

    #[Test]
    public function shouldRetryWhenRetryableAndUnderMaxAttempts(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);

        $this->assertTrue($policy->shouldRetry(new PHPMailerException('SMTP timeout'), 1));
        $this->assertTrue($policy->shouldRetry(new PHPMailerException('SMTP timeout'), 2));
    }

    #[Test]
    public function shouldNotRetryOnceMaxAttemptsReached(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);

        $this->assertFalse($policy->shouldRetry(new PHPMailerException('SMTP timeout'), 3));
    }

    #[Test]
    public function shouldNotRetryNonRetryableExceptionEvenOnFirstAttempt(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);

        $this->assertFalse($policy->shouldRetry(new \InvalidArgumentException('bad payload'), 1));
    }
}
