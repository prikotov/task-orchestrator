<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Common\Module\AgentRole\Application\Exception\ResolveRoleSkillsFailedException;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQuery;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQueryHandler;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleArgumentNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleArgumentServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadRoleFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

#[CoversClass(ResolveRoleSkillsQueryHandler::class)]
final class ResolveRoleSkillsQueryHandlerTest extends TestCase
{
    private ResolveRoleArgumentServiceInterface $argumentResolver;
    private LoadRoleFrontmatterServiceInterface $roleReader;
    private ResolveRoleSkillsServiceInterface $resolver;
    private FormatSkillCatalogServiceInterface $formatter;
    private ResolveRoleSkillsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->argumentResolver = $this->createStub(ResolveRoleArgumentServiceInterface::class);
        $this->roleReader = $this->createStub(LoadRoleFrontmatterServiceInterface::class);
        $this->resolver = $this->createStub(ResolveRoleSkillsServiceInterface::class);
        $this->formatter = $this->createStub(FormatSkillCatalogServiceInterface::class);

        $this->handler = new ResolveRoleSkillsQueryHandler(
            $this->argumentResolver,
            $this->roleReader,
            $this->resolver,
            $this->formatter,
            new Filesystem(),
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

        $this->argumentResolver->method('resolve')->willReturn($roleFile);
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
        self::assertSame('team_lead_alex', $result->roleName);
        self::assertSame('docs/agents/roles/team/team_lead_alex.ru.md', $result->roleFilePath);
    }

    #[Test]
    public function invokeWrapsDomainExceptionIntoApplicationBoundaryException(): void
    {
        // Arrange
        $this->argumentResolver
            ->method('resolve')
            ->willThrowException(new RoleArgumentNotFoundException('missing_role.md', []));

        // Assert
        $this->expectException(ResolveRoleSkillsFailedException::class);

        // Act
        ($this->handler)(new ResolveRoleSkillsQuery('missing_role.md'));
    }

    #[Test]
    public function invokePassesPathBasesToArgumentResolver(): void
    {
        // Arrange: Query несёт базисы (cwd, логический PWD) — Handler передаёт
        // их в резолвер аргумента без изменений.
        $this->argumentResolver
            ->method('resolve')
            ->willReturnCallback(
                function (string $argument, array $bases): string {
                    self::assertSame('docs/agents/roles/team/team_lead_alex.ru.md', $argument);
                    self::assertSame(['/abs/cwd', '/abs/logical'], $bases);

                    return '/abs/project/docs/agents/roles/team/team_lead_alex.ru.md';
                },
            );
        $this->roleReader->method('read')->willReturn(new RoleMetadataVo(
            name: RoleNameVo::createFromName('team_lead_alex'),
            filePath: '/abs/project/docs/agents/roles/team/team_lead_alex.ru.md',
            skills: [],
        ));
        $this->resolver->method('resolve')->willReturn([]);
        $this->formatter->method('format')->willReturn('');

        // Act
        ($this->handler)(new ResolveRoleSkillsQuery(
            'docs/agents/roles/team/team_lead_alex.ru.md',
            ['/abs/cwd', '/abs/logical'],
        ));
    }
}
