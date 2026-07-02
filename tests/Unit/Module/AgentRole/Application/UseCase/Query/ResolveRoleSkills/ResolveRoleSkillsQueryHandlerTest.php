<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Application\Exception\ResolveRoleSkillsFailedException;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQuery;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQueryHandler;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleFileNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LocateRoleFileServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadRoleFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

#[CoversClass(ResolveRoleSkillsQueryHandler::class)]
final class ResolveRoleSkillsQueryHandlerTest extends TestCase
{
    private LocateRoleFileServiceInterface $locator;
    private LoadRoleFrontmatterServiceInterface $roleReader;
    private ResolveRoleSkillsServiceInterface $resolver;
    private FormatSkillCatalogServiceInterface $formatter;
    private ResolveRoleSkillsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->locator = $this->createMock(LocateRoleFileServiceInterface::class);
        $this->roleReader = $this->createMock(LoadRoleFrontmatterServiceInterface::class);
        $this->resolver = $this->createMock(ResolveRoleSkillsServiceInterface::class);
        $this->formatter = $this->createMock(FormatSkillCatalogServiceInterface::class);

        $this->handler = new ResolveRoleSkillsQueryHandler(
            $this->locator,
            $this->roleReader,
            $this->resolver,
            $this->formatter,
            basePath: '/abs/project',
        );
    }

    #[Test]
    public function invokeResolvesRoleSkillsAndCatalogBlock(): void
    {
        // Arrange
        $roleFile = '/abs/project/docs/agents/roles/team/team_lead_alex.ru.md';
        $roleMetadata = new RoleMetadataVo(
            name: RoleNameVo::createFromName('team_lead_alex'),
            filePath: $roleFile,
            skills: [SkillNameVo::createFromName('run-subagent')],
        );
        $skills = [
            new SkillMetadataVo(
                name: SkillNameVo::createFromName('run-subagent'),
                description: 'Запуск сабагента',
                location: '/abs/skills/run-subagent/SKILL.md',
            ),
        ];

        $this->locator->method('locate')->willReturn($roleFile);
        $this->roleReader->method('read')->willReturn($roleMetadata);
        $this->resolver->method('resolve')->willReturn($skills);
        $this->formatter->method('format')->willReturn('<available_skills>...</available_skills>');

        // Act
        $result = ($this->handler)(new ResolveRoleSkillsQuery('team_lead_alex'));

        // Assert
        self::assertCount(1, $result->skills);
        self::assertSame('run-subagent', $result->skills[0]->name);
        self::assertSame('Запуск сабагента', $result->skills[0]->description);
        self::assertSame('<available_skills>...</available_skills>', $result->catalogBlock);
        self::assertSame('docs/agents/roles/team/team_lead_alex.ru.md', $result->roleFilePath);
    }

    #[Test]
    public function invokeWrapsDomainExceptionIntoApplicationBoundaryException(): void
    {
        // Arrange
        $this->locator
            ->method('locate')
            ->willThrowException(new RoleFileNotFoundException('missing_role', '/abs/missing.md'));

        // Assert
        $this->expectException(ResolveRoleSkillsFailedException::class);

        // Act
        ($this->handler)(new ResolveRoleSkillsQuery('missing_role'));
    }
}
