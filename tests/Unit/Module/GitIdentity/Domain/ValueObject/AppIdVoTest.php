<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;

#[CoversClass(AppIdVo::class)]
final class AppIdVoTest extends TestCase
{
    #[Test]
    public function positiveIntegerIsAcceptedAndReturned(): void
    {
        $vo = new AppIdVo(123456);

        self::assertSame(123456, $vo->getValue());
    }

    #[Test]
    public function unitValueIsAccepted(): void
    {
        $vo = new AppIdVo(1);

        self::assertSame(1, $vo->getValue());
    }

    #[Test]
    public function zeroValueThrowsInvalidConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('GitHub App ID must be a positive integer');

        new AppIdVo(0);
    }

    #[Test]
    public function negativeValueThrowsInvalidConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new AppIdVo(-1);
    }

    #[Test]
    public function invalidConfigurationIsGitIdentityException(): void
    {
        try {
            new AppIdVo(-42);
            self::fail('Expected InvalidConfigurationException');
        } catch (InvalidConfigurationException $e) {
            self::assertInstanceOf(GitIdentityException::class, $e);
        }
    }

    #[Test]
    public function equalsReturnsTrueForSameValue(): void
    {
        $a = new AppIdVo(42);
        $b = new AppIdVo(42);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentValue(): void
    {
        $a = new AppIdVo(42);
        $b = new AppIdVo(43);

        self::assertFalse($a->equals($b));
    }
}
