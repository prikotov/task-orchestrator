<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\DynamicLoop\Domain\Helper;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper\DynamicRoleCommandHelper;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;

#[CoversClass(DynamicRoleCommandHelper::class)]
final class DynamicRoleCommandHelperTest extends TestCase
{
    // ─── parseRunner ──────────────────────────────────────────────────

    #[Test]
    public function parseRunnerReturnsFirstCommandElement(): void
    {
        $config = new DynamicLoopRoleConfigVo(command: ['pi', '--model', 'gpt-4'], timeout: 60);

        self::assertSame('pi', DynamicRoleCommandHelper::parseRunner($config));
    }

    #[Test]
    public function parseRunnerThrowsOnNullConfig(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Role configuration must define a non-empty command');

        DynamicRoleCommandHelper::parseRunner(null);
    }

    #[Test]
    public function parseRunnerThrowsOnEmptyCommand(): void
    {
        $config = new DynamicLoopRoleConfigVo(command: [], timeout: 60);

        $this->expectException(LogicException::class);

        DynamicRoleCommandHelper::parseRunner($config);
    }

    #[Test]
    public function parseRunnerThrowsOnEmptyFirstElement(): void
    {
        $config = new DynamicLoopRoleConfigVo(command: ['', '--model'], timeout: 60);

        $this->expectException(LogicException::class);

        DynamicRoleCommandHelper::parseRunner($config);
    }

    // ─── parseThinking (thinking-уровень из команды роли) ─────────────

    #[Test]
    public function parseThinkingExtractsValueAfterFlag(): void
    {
        $config = new DynamicLoopRoleConfigVo(
            command: ['pi', '--mode', 'json', '--provider', 'zai', '--model', 'glm-5.3-flash', '--thinking', 'high'],
        );

        self::assertSame('high', DynamicRoleCommandHelper::parseThinking($config));
    }

    #[Test]
    public function parseThinkingReturnsNullWhenFlagMissing(): void
    {
        $config = new DynamicLoopRoleConfigVo(
            command: ['pi', '--mode', 'json', '--provider', 'zai', '--model', 'glm-5.3-flash'],
        );

        self::assertNull(DynamicRoleCommandHelper::parseThinking($config));
    }

    #[Test]
    public function parseThinkingReturnsNullWhenFlagIsLastWithoutValue(): void
    {
        $config = new DynamicLoopRoleConfigVo(
            command: ['pi', '--model', 'glm-5.3-flash', '--thinking'],
        );

        self::assertNull(DynamicRoleCommandHelper::parseThinking($config));
    }

    #[Test]
    public function parseThinkingReturnsNullWhenNextPartIsAnotherFlag(): void
    {
        $config = new DynamicLoopRoleConfigVo(
            command: ['pi', '--thinking', '--model', 'glm-5.3-flash'],
        );

        self::assertNull(DynamicRoleCommandHelper::parseThinking($config));
    }

    #[Test]
    public function parseThinkingReturnsNullForNullConfig(): void
    {
        self::assertNull(DynamicRoleCommandHelper::parseThinking(null));
    }
}
