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
        $formatter = new FormatSkillCatalogService('en');

        // Act
        $result = $formatter->format([]);

        // Assert
        self::assertSame('', $result);
    }

    #[Test]
    public function formatSingleSkillProducesAgentskillsXmlBlock(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService('ru');
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
        $formatter = new FormatSkillCatalogService('ru');
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

    #[Test]
    public function formatWithRuLocaleProducesRussianHeader(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService('ru');

        // Act
        $result = $formatter->format([$this->buildSkill('demo', 'desc', '/d/SKILL.md')]);

        // Assert: русскоязычный header.
        self::assertStringContainsString('Следующие skills предоставляют', $result);
        self::assertStringContainsString('загрузи его SKILL.md инструментом read', $result);
        self::assertStringNotContainsString('The following skills provide', $result);
    }

    #[Test]
    public function formatWithEnLocaleProducesEnglishHeader(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService('en');

        // Act
        $result = $formatter->format([$this->buildSkill('demo', 'desc', '/d/SKILL.md')]);

        // Assert: англоязычный header (формат pi formatSkillsForPrompt).
        self::assertStringContainsString('The following skills provide specialized instructions', $result);
        self::assertStringContainsString("skill's description — load its SKILL.md using the read tool", $result);
        self::assertStringNotContainsString('Следующие skills', $result);
    }

    #[Test]
    public function formatWithZhLocaleProducesChineseHeader(): void
    {
        // Arrange
        $formatter = new FormatSkillCatalogService('zh');

        // Act
        $result = $formatter->format([$this->buildSkill('demo', 'desc', '/d/SKILL.md')]);

        // Assert: перевод header'а на китайский.
        self::assertStringContainsString('以下技能为特定任务提供专门说明', $result);
        self::assertStringContainsString('用 read 工具加载该技能的 SKILL.md', $result);
        self::assertStringNotContainsString('The following skills provide', $result);
    }

    #[Test]
    public function formatWithUnknownLocaleFallsBackToEnglishHeader(): void
    {
        // Arrange: неизвестная локаль → fallback на en (default библиотеки).
        $formatter = new FormatSkillCatalogService('fr');

        // Act
        $result = $formatter->format([$this->buildSkill('demo', 'desc', '/d/SKILL.md')]);

        // Assert
        self::assertStringContainsString('The following skills provide specialized instructions', $result);
        self::assertStringNotContainsString('Следующие skills', $result);
        self::assertStringNotContainsString('以下技能', $result);
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
