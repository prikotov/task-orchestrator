<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\ValueObject;

use TasK\Orchestrator\Domain\ValueObject\RoleConfigVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleConfigVo::class)]
final class RoleConfigVoTest extends TestCase
{
    #[Test]
    public function createWithDefaults(): void
    {
        $vo = new RoleConfigVo();

        self::assertSame([], $vo->getCommand());
        self::assertNull($vo->getTimeout());
        self::assertNull($vo->getPromptFile());
    }

    #[Test]
    public function createWithAllFields(): void
    {
        $vo = new RoleConfigVo(
            command: ['pi', '--mode', 'json', '-p', '--no-session', '--model', 'gpt-4o-mini'],
            timeout: 600,
            promptFile: 'docs/agents/roles/team/backend_developer.ru.md',
        );

        self::assertSame(['pi', '--mode', 'json', '-p', '--no-session', '--model', 'gpt-4o-mini'], $vo->getCommand());
        self::assertSame(600, $vo->getTimeout());
        self::assertSame('docs/agents/roles/team/backend_developer.ru.md', $vo->getPromptFile());
    }

    #[Test]
    public function createWithPromptFileOnly(): void
    {
        $vo = new RoleConfigVo(
            promptFile: 'docs/agents/roles/team/system_analyst.ru.md',
        );

        self::assertSame([], $vo->getCommand());
        self::assertNull($vo->getTimeout());
        self::assertSame('docs/agents/roles/team/system_analyst.ru.md', $vo->getPromptFile());
    }

    #[Test]
    public function createWithoutPromptFileReturnsNull(): void
    {
        $vo = new RoleConfigVo(
            command: ['pi'],
            timeout: 300,
        );

        self::assertNull($vo->getPromptFile());
    }
}
