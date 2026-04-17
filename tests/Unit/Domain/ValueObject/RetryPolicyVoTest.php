<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPolicyVo::class)]
final class RetryPolicyVoTest extends TestCase
{
    #[Test]
    public function defaultConstructorSetsExpectedValues(): void
    {
        $vo = new RetryPolicyVo();

        self::assertSame(3, $vo->getMaxRetries());
        self::assertSame(1000, $vo->getInitialDelayMs());
        self::assertSame(30000, $vo->getMaxDelayMs());
        self::assertSame(2.0, $vo->getMultiplier());
        self::assertTrue($vo->isEnabled());
    }

    #[Test]
    public function customConstructorSetsProvidedValues(): void
    {
        $vo = new RetryPolicyVo(
            maxRetries: 5,
            initialDelayMs: 500,
            maxDelayMs: 60000,
            multiplier: 3.0,
        );

        self::assertSame(5, $vo->getMaxRetries());
        self::assertSame(500, $vo->getInitialDelayMs());
        self::assertSame(60000, $vo->getMaxDelayMs());
        self::assertSame(3.0, $vo->getMultiplier());
    }

    #[Test]
    public function isEnabledReturnsTrueWhenMaxRetriesGreaterThanZero(): void
    {
        $vo = new RetryPolicyVo(maxRetries: 1);
        self::assertTrue($vo->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenMaxRetriesIsZero(): void
    {
        $vo = new RetryPolicyVo(maxRetries: 0);
        self::assertFalse($vo->isEnabled());
    }

    #[Test]
    public function disabledCreatesPolicyWithZeroRetries(): void
    {
        $vo = RetryPolicyVo::disabled();

        self::assertSame(0, $vo->getMaxRetries());
        self::assertFalse($vo->isEnabled());
    }

    #[Test]
    public function getDelayForAttemptReturnsCorrectExponentialBackoff(): void
    {
        $vo = new RetryPolicyVo(
            initialDelayMs: 1000,
            maxDelayMs: 30000,
            multiplier: 2.0,
        );

        // attempt 0: 1000 * 2^0 = 1000
        self::assertSame(1000, $vo->getDelayForAttempt(0));
        // attempt 1: 1000 * 2^1 = 2000
        self::assertSame(2000, $vo->getDelayForAttempt(1));
        // attempt 2: 1000 * 2^2 = 4000
        self::assertSame(4000, $vo->getDelayForAttempt(2));
        // attempt 3: 1000 * 2^3 = 8000
        self::assertSame(8000, $vo->getDelayForAttempt(3));
        // attempt 4: 1000 * 2^4 = 16000
        self::assertSame(16000, $vo->getDelayForAttempt(4));
        // attempt 5: 1000 * 2^5 = 32000, capped at 30000
        self::assertSame(30000, $vo->getDelayForAttempt(5));
    }

    #[Test]
    public function getDelayForAttemptReturnsZeroForNegativeAttempt(): void
    {
        $vo = new RetryPolicyVo();

        self::assertSame(0, $vo->getDelayForAttempt(-1));
    }

    #[Test]
    public function getDelayForAttemptRespectsMaxDelay(): void
    {
        $vo = new RetryPolicyVo(
            initialDelayMs: 1000,
            maxDelayMs: 5000,
            multiplier: 10.0,
        );

        // attempt 0: 1000 * 10^0 = 1000
        self::assertSame(1000, $vo->getDelayForAttempt(0));
        // attempt 1: 1000 * 10^1 = 10000, capped at 5000
        self::assertSame(5000, $vo->getDelayForAttempt(1));
    }

    #[Test]
    public function fromArrayCreatesPolicyWithProvidedValues(): void
    {
        $vo = RetryPolicyVo::fromArray([
            'max_retries' => 5,
            'initial_delay_ms' => 200,
            'max_delay_ms' => 10000,
            'multiplier' => 1.5,
        ]);

        self::assertSame(5, $vo->getMaxRetries());
        self::assertSame(200, $vo->getInitialDelayMs());
        self::assertSame(10000, $vo->getMaxDelayMs());
        self::assertSame(1.5, $vo->getMultiplier());
    }

    #[Test]
    public function fromArrayUsesDefaultsForMissingKeys(): void
    {
        $vo = RetryPolicyVo::fromArray([]);

        self::assertSame(3, $vo->getMaxRetries());
        self::assertSame(1000, $vo->getInitialDelayMs());
        self::assertSame(30000, $vo->getMaxDelayMs());
        self::assertSame(2.0, $vo->getMultiplier());
    }

    #[Test]
    public function fromArrayUsesPartialOverrides(): void
    {
        $vo = RetryPolicyVo::fromArray(['max_retries' => 7]);

        self::assertSame(7, $vo->getMaxRetries());
        self::assertSame(1000, $vo->getInitialDelayMs());
    }

    #[Test]
    public function toLogStringContainsAllParameters(): void
    {
        $vo = new RetryPolicyVo(
            maxRetries: 3,
            initialDelayMs: 1000,
            maxDelayMs: 30000,
            multiplier: 2.0,
        );

        $logString = $vo->toLogString();

        self::assertStringContainsString('maxRetries=3', $logString);
        self::assertStringContainsString('initialDelayMs=1000', $logString);
        self::assertStringContainsString('maxDelayMs=30000', $logString);
        self::assertStringContainsString('multiplier=2.0', $logString);
    }

    #[Test]
    public function throwsOnNegativeMaxRetries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRetries must be >= 0');

        new RetryPolicyVo(maxRetries: -1);
    }

    #[Test]
    public function throwsOnNegativeInitialDelayMs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('initialDelayMs must be >= 0');

        new RetryPolicyVo(initialDelayMs: -100);
    }

    #[Test]
    public function throwsOnNegativeMaxDelayMs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDelayMs must be >= 0');

        new RetryPolicyVo(maxDelayMs: -1);
    }

    #[Test]
    public function throwsOnMultiplierLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiplier must be >= 1.0');

        new RetryPolicyVo(multiplier: 0.5);
    }

    #[Test]
    public function acceptsZeroInitialDelayAndMaxDelay(): void
    {
        $vo = new RetryPolicyVo(initialDelayMs: 0, maxDelayMs: 0);

        self::assertSame(0, $vo->getInitialDelayMs());
        self::assertSame(0, $vo->getMaxDelayMs());
        self::assertSame(0, $vo->getDelayForAttempt(0));
    }

    #[Test]
    public function acceptsMultiplierExactlyOne(): void
    {
        $vo = new RetryPolicyVo(multiplier: 1.0, initialDelayMs: 1000);

        self::assertSame(1.0, $vo->getMultiplier());
        // С множителем 1.0 задержка не растёт
        self::assertSame(1000, $vo->getDelayForAttempt(0));
        self::assertSame(1000, $vo->getDelayForAttempt(5));
    }
}
