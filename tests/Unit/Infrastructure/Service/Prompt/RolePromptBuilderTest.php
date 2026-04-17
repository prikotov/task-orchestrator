<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Prompt;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RoleNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Prompt\RolePromptBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RolePromptBuilder::class)]
final class RolePromptBuilderTest extends TestCase
{
    private string $fixtureDir;
    private RolePromptBuilder $builder;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/agent_roles_test_' . uniqid();
        mkdir($this->fixtureDir);

        file_put_contents(
            $this->fixtureDir . '/system_analyst.ru.md',
            "# System Analyst (Аналитик)\n\nAnalyze requirements.",
        );
        file_put_contents(
            $this->fixtureDir . '/backend_developer.ru.md',
            "# Backend Developer (Бэкендер)\n\nWrite code.",
        );

        $this->builder = new RolePromptBuilder($this->fixtureDir, sys_get_temp_dir());
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->fixtureDir . '/*.md') ?: []);
        rmdir($this->fixtureDir);
    }

    #[Test]
    public function getPromptReturnsFileContent(): void
    {
        $prompt = $this->builder->getPrompt('system_analyst');

        self::assertStringContainsString('System Analyst', $prompt);
        self::assertStringContainsString('Analyze requirements.', $prompt);
    }

    #[Test]
    public function getPromptThrowsOnUnknownRole(): void
    {
        $this->expectException(RoleNotFoundException::class);
        $this->expectExceptionMessage('nonexistent');

        $this->builder->getPrompt('nonexistent');
    }

    #[Test]
    public function roleExistsReturnsTrueForExisting(): void
    {
        self::assertTrue($this->builder->roleExists('system_analyst'));
        self::assertTrue($this->builder->roleExists('backend_developer'));
    }

    #[Test]
    public function roleExistsReturnsFalseForMissing(): void
    {
        self::assertFalse($this->builder->roleExists('unknown'));
    }

    #[Test]
    public function getPromptFilePathReturnsRelativeFromProjectDir(): void
    {
        $path = $this->builder->getPromptFilePath('system_analyst');

        self::assertStringContainsString('system_analyst.ru.md', $path);
        // Относительный путь от projectDir
        self::assertStringStartsWith('agent_roles_test_', basename(dirname($path)));
        self::assertFalse(str_starts_with($path, '/'));
    }

    #[Test]
    public function getAvailableRolesReturnsAll(): void
    {
        $roles = $this->builder->getAvailableRoles();

        self::assertCount(2, $roles);
        self::assertArrayHasKey('system_analyst', $roles);
        self::assertArrayHasKey('backend_developer', $roles);
        self::assertStringContainsString('System Analyst', $roles['system_analyst']);
    }
}
