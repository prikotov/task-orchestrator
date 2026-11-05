<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
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

    #[Test]
    public function createThrowsOnInvalidComplexityLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid task complexity level "C9".');

        new RoleConfigVo(commandsByComplexity: ['C9' => ['--model', 'max']]);
    }

    #[Test]
    public function createThrowsOnEmptyComplexityCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Command for task complexity "C1" must not be empty.');

        new RoleConfigVo(commandsByComplexity: ['C1' => []]);
    }

    // --- resolveCommandByComplexity (правило: точный уровень > ближайший выше > ближайший ниже) ---

    #[Test]
    public function resolveReturnsExactLevelCommand(): void
    {
        $vo = new RoleConfigVo(
            command: ['--model', 'base'],
            commandsByComplexity: [
                'C0' => ['--model', 'low'],
                'C5' => ['--model', 'max'],
            ],
        );

        self::assertSame(['--model', 'low'], $vo->resolveCommandByComplexity(0));
        self::assertSame(['--model', 'max'], $vo->resolveCommandByComplexity(5));
    }

    #[Test]
    public function resolveFallsBackToNearestConfiguredLevelBelow(): void
    {
        // Базовая команда неявно регистрируется как C3: C4-C5 уходят вниз к C3.
        $vo = new RoleConfigVo(
            command: ['--model', 'base'],
            commandsByComplexity: ['C0' => ['--model', 'low']],
        );

        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(4));
        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(5));
    }

    #[Test]
    public function resolveFallsBackToNearestConfiguredLevelAbove(): void
    {
        // Заданы C0 и C5, запрос C4: точного нет, вверх раньше неявного C3 находится C5.
        $vo = new RoleConfigVo(
            command: ['--model', 'base'],
            commandsByComplexity: ['C0' => ['--model', 'low'], 'C5' => ['--model', 'max']],
        );

        self::assertSame(['--model', 'max'], $vo->resolveCommandByComplexity(4));

        // Без базовой команды неявный C3 не регистрируется: C0-C2 поднимаются к ближайшему выше C4.
        $baseless = new RoleConfigVo(
            commandsByComplexity: ['C4' => ['--model', 'high']],
        );

        self::assertSame(['--model', 'high'], $baseless->resolveCommandByComplexity(0));
        self::assertSame(['--model', 'high'], $baseless->resolveCommandByComplexity(3));
    }

    #[Test]
    public function resolveUsesBaseCommandAsImplicitC3(): void
    {
        $vo = new RoleConfigVo(
            command: ['--model', 'base'],
            commandsByComplexity: ['C0' => ['--model', 'low'], 'C5' => ['--model', 'max']],
        );

        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(3));
        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(2));
    }

    #[Test]
    public function resolvePrefersExplicitC3OverBaseCommand(): void
    {
        $vo = new RoleConfigVo(
            command: ['--model', 'base'],
            commandsByComplexity: ['C3' => ['--model', 'explicit-c3']],
        );

        self::assertSame(['--model', 'explicit-c3'], $vo->resolveCommandByComplexity(3));
    }

    #[Test]
    public function resolveReturnsBaseCommandWithoutDeviationMap(): void
    {
        $vo = new RoleConfigVo(command: ['--model', 'base']);

        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(0));
        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(3));
        self::assertSame(['--model', 'base'], $vo->resolveCommandByComplexity(5));
    }

    #[Test]
    public function resolveThrowsOnComplexityOutOfRange(): void
    {
        $vo = new RoleConfigVo(command: ['--model', 'base']);

        try {
            $vo->resolveCommandByComplexity(6);
            self::fail('Expected InvalidArgumentException for complexity 6.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Invalid task complexity 6: expected 0-5 (C0-C5).', $e->getMessage());
        }

        try {
            $vo->resolveCommandByComplexity(-1);
            self::fail('Expected InvalidArgumentException for complexity -1.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Invalid task complexity -1: expected 0-5 (C0-C5).', $e->getMessage());
        }
    }
}
