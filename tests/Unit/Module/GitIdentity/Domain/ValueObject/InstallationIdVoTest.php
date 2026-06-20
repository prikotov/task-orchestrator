<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;

#[CoversClass(InstallationIdVo::class)]
final class InstallationIdVoTest extends TestCase
{
    #[Test]
    public function positiveValueIsAccepted(): void
    {
        $vo = new InstallationIdVo(987654321);

        self::assertSame(987654321, $vo->getValue());
    }

    #[Test]
    public function cacheKeyIsStringValue(): void
    {
        $vo = new InstallationIdVo(42);

        self::assertSame('42', $vo->cacheKey());
    }

    #[Test]
    public function zeroThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new InstallationIdVo(0);
    }

    #[Test]
    public function negativeThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new InstallationIdVo(-5);
    }

    #[Test]
    public function equalsComparesValues(): void
    {
        self::assertTrue((new InstallationIdVo(7))->equals(new InstallationIdVo(7)));
        self::assertFalse((new InstallationIdVo(7))->equals(new InstallationIdVo(8)));
    }
}
