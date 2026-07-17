<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Domain\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogService;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

#[CoversClass(FormatSkillCatalogService::class)]
final class FormatSkillCatalogServiceTest extends TestCase
{
    #[Test]
    public function formatEmptySkillsReturnsEmptyString(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService();

        // Act
        $result = $formatter->format([]);

        // Assert
        self::assertSame('', $result);
    }

    #[Test]
    public function formatSingleSkillProducesAgentskillsXmlBlock(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService();
        $skill = $this->buildSkill('run-subagent', 'Запуск сабагента', '/abs/run-subagent/SKILL.md');

        // Act
        $result = $formatter->format([$skill]);

        // Assert
        self::assertStringContainsString('<available_skills>', $result);
        self::assertStringContainsString('</available_skills>', $result);
        self::assertStringContainsString('<name>run-subagent</name>', $result);
        self::assertStringContainsString('<description>Запуск сабагента</description>', $result);
        self::assertStringContainsString('<location>/abs/run-subagent/SKILL.md</location>', $result);
        self::assertStringContainsString('загрузи его SKILL.md инструментом read', $result);
        self::assertStringContainsString('лежат рядом с SKILL.md', $result);
    }

    #[Test]
    public function formatEscapesXmlSpecialCharactersInValues(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService();
        $skill = $this->buildSkill(
            'pdf-processing',
            'Extract <PDF> & "fill" forms \'now\'',
            '/abs/path/SKILL.md',
        );

        // Act
        $result = $formatter->format([$skill]);

        // Assert
        self::assertStringContainsString('&lt;PDF&gt;', $result);
        self::assertStringContainsString('&amp;', $result);
        self::assertStringContainsString('&quot;fill&quot;', $result);
        self::assertStringContainsString('&apos;now&apos;', $result);
    }

    private function buildSkill(string $name, string $description, string $location): SkillMetadataVo
    {
        return new SkillMetadataVo(
            name: SkillNameVo::createFromName($name),
            description: $description,
            location: $location,
            dependsOn: [],
        );
    }
}
