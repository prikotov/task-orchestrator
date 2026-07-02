<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

#[CoversClass(SkillNameVo::class)]
final class SkillNameVoTest extends TestCase
{
    #[Test]
    public function createFromNameAcceptsValidKebabCaseNames(): void
    {
        $vo = SkillNameVo::createFromName('run-subagent');

        self::assertSame('run-subagent', $vo->value());
        self::assertSame('run-subagent', (string) $vo);
    }

    #[Test]
    public function createFromNameAcceptsSingleSegmentAndDigits(): void
    {
        self::assertSame('a', SkillNameVo::createFromName('a')->value());
        self::assertSame('skill1', SkillNameVo::createFromName('skill1')->value());
    }

    #[Test]
    public function createFromNameRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SkillNameVo::createFromName('   ');
    }

    #[Test]
    public function createFromNameRejectsNameExceeding64Chars(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SkillNameVo::createFromName(str_repeat('a', 65));
    }

    #[Test]
    public function createFromNameRejectsUppercaseAndNonLatin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SkillNameVo::createFromName('Run-Subagent');
    }

    #[Test]
    public function createFromNameRejectsLeadingHyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SkillNameVo::createFromName('-run');
    }

    #[Test]
    public function createFromNameRejectsTrailingHyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SkillNameVo::createFromName('run-');
    }

    #[Test]
    public function createFromNameRejectsConsecutiveHyphens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SkillNameVo::createFromName('run--subagent');
    }

    #[Test]
    public function equalsReturnsTrueOnlyForSameValue(): void
    {
        self::assertTrue(SkillNameVo::createFromName('run-subagent')->equals(SkillNameVo::createFromName('run-subagent')));
        self::assertFalse(SkillNameVo::createFromName('run-subagent')->equals(SkillNameVo::createFromName('other')));
    }
}
