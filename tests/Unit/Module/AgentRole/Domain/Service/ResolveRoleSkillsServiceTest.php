<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Domain\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\CircularSkillDependencyException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsService;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadSkillFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

use function array_map;

#[CoversClass(ResolveRoleSkillsService::class)]
final class ResolveRoleSkillsServiceTest extends TestCase
{
    private LoadSkillFrontmatterServiceInterface $skillReader;
    private ResolveRoleSkillsService $resolver;

    /** @var array<string, list<string>> skill name → depends_on names */
    private array $dependenciesGraph = [];

    protected function setUp(): void
    {
        $this->skillReader = $this->createMock(LoadSkillFrontmatterServiceInterface::class);
        $this->skillReader
            ->method('read')
            ->willReturnCallback(fn (SkillNameVo $name): SkillMetadataVo => $this->buildSkillWithDeps($name));

        $this->resolver = new ResolveRoleSkillsService($this->skillReader);
    }

    #[Test]
    public function resolveReturnsSkillsInDeclaredOrderWithoutDependencies(): void
    {
        // Arrange
        $role = $this->buildRole(['alpha', 'beta'], ['alpha' => [], 'beta' => []]);

        // Act
        $result = $this->resolver->resolve($role);

        // Assert
        $this->assertSkillNames(['alpha', 'beta'], $result);
    }

    #[Test]
    public function resolvePutsDependencyBeforeDependent(): void
    {
        // Arrange: alpha depends on beta → [beta, alpha]
        $role = $this->buildRole(['alpha'], ['alpha' => ['beta'], 'beta' => []]);

        // Act
        $result = $this->resolver->resolve($role);

        // Assert
        $this->assertSkillNames(['beta', 'alpha'], $result);
    }

    #[Test]
    public function resolveExpandsTransitiveDependencies(): void
    {
        // Arrange: alpha → beta → gamma → [gamma, beta, alpha]
        $role = $this->buildRole(['alpha'], ['alpha' => ['beta'], 'beta' => ['gamma'], 'gamma' => []]);

        // Act
        $result = $this->resolver->resolve($role);

        // Assert
        $this->assertSkillNames(['gamma', 'beta', 'alpha'], $result);
    }

    #[Test]
    public function resolveDeduplicatesSharedDependency(): void
    {
        // Arrange: alpha and beta both depend on common → [common, alpha, beta]
        $role = $this->buildRole(
            ['alpha', 'beta'],
            ['alpha' => ['common'], 'beta' => ['common'], 'common' => []],
        );

        // Act
        $result = $this->resolver->resolve($role);

        // Assert
        $this->assertSkillNames(['common', 'alpha', 'beta'], $result);
    }

    #[Test]
    public function resolveThrowsOnCircularDependency(): void
    {
        // Arrange: alpha → beta → alpha
        $role = $this->buildRole(['alpha'], ['alpha' => ['beta'], 'beta' => ['alpha']]);

        // Assert
        $this->expectException(CircularSkillDependencyException::class);

        // Act
        $this->resolver->resolve($role);
    }

    /**
     * @param list<string> $skills
     * @param array<string, list<string>> $graph
     */
    private function buildRole(array $skills, array $graph): RoleMetadataVo
    {
        $this->dependenciesGraph = $graph;

        return new RoleMetadataVo(
            name: RoleNameVo::createFromName('test_role'),
            filePath: '/tmp/test_role.ru.md',
            skills: array_map(SkillNameVo::createFromName(...), $skills),
        );
    }

    private function buildSkillWithDeps(SkillNameVo $name): SkillMetadataVo
    {
        $deps = $this->dependenciesGraph[$name->value()] ?? [];

        return new SkillMetadataVo(
            name: $name,
            description: 'Description of ' . $name->value(),
            location: '/abs/' . $name->value() . '/SKILL.md',
            dependsOn: array_map(SkillNameVo::createFromName(...), $deps),
        );
    }

    /**
     * @param list<string> $expected
     * @param list<SkillMetadataVo> $actual
     */
    private function assertSkillNames(array $expected, array $actual): void
    {
        $actualNames = array_map(static fn (SkillMetadataVo $skill): string => $skill->getName()->value(), $actual);

        self::assertSame($expected, $actualNames);
    }
}
