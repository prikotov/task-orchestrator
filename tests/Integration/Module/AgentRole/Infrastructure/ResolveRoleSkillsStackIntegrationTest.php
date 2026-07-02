<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\AgentRole\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQuery;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQueryHandler;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogService;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsService;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Component\Frontmatter\FrontmatterYamlParser;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemLocateRoleFileService;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\YamlLoadRoleFrontmatterService;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\YamlLoadSkillFrontmatterService;

/**
 * Integration-тест полного стека резолвинга skills роли на реальных файлах fixtures.
 *
 * Проверяет связку locator → role reader → skill reader (Symfony YAML) →
 * resolver (depends_on) → formatter (XML) на реальной файловой системе.
 */
#[Group('integration')]
#[CoversClass(ResolveRoleSkillsQueryHandler::class)]
#[CoversClass(FilesystemLocateRoleFileService::class)]
#[CoversClass(YamlLoadRoleFrontmatterService::class)]
#[CoversClass(YamlLoadSkillFrontmatterService::class)]
final class ResolveRoleSkillsStackIntegrationTest extends TestCase
{
    private string $rolesDir;

    private string $skillsDir;

    private ResolveRoleSkillsQueryHandler $handler;

    protected function setUp(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';
        $this->rolesDir = $fixturesDir . '/roles';
        $this->skillsDir = $fixturesDir . '/skills';

        $parser = new FrontmatterYamlParser();

        $this->handler = new ResolveRoleSkillsQueryHandler(
            roleFileLocator: new FilesystemLocateRoleFileService($this->rolesDir),
            roleFrontmatterReader: new YamlLoadRoleFrontmatterService($parser),
            roleSkillsResolver: new ResolveRoleSkillsService(new YamlLoadSkillFrontmatterService($this->skillsDir, $parser)),
            skillCatalogFormatter: new FormatSkillCatalogService(),
            filesystem: new Filesystem(),
            basePath: $fixturesDir,
        );
    }

    #[Test]
    public function resolveSampleRoleExpandsDependenciesAndProducesXmlCatalog(): void
    {
        // Arrange
        // sample_role декларирует [run-subagent, epic-via-subagents];
        // epic-via-subagents зависит от run-subagent → ожидаем [run-subagent, epic-via-subagents].

        // Act
        $result = ($this->handler)(new ResolveRoleSkillsQuery('sample_role'));

        // Assert
        self::assertCount(2, $result->skills);
        self::assertSame('run-subagent', $result->skills[0]->name);
        self::assertSame('epic-via-subagents', $result->skills[1]->name);

        self::assertStringContainsString('<available_skills>', $result->catalogBlock);
        self::assertStringContainsString('<name>run-subagent</name>', $result->catalogBlock);
        self::assertStringContainsString('<name>epic-via-subagents</name>', $result->catalogBlock);
        self::assertStringContainsString('<location>', $result->catalogBlock);
        self::assertStringContainsString('SKILL.md', $result->catalogBlock);
    }
}
