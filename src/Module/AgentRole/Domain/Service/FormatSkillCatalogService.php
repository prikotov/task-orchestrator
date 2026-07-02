<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use Override;



/**
 * XML-форматтер каталога skills (стандарт Agent Skills / pi).
 *
 * Воспроизводит дословный формат pi `formatSkillsForPrompt` (dist/core/skills.js),
 * чтобы модель встречала знакомую структуру и в нативных pi-сессиях, и в
 * сабагентах, и в codex (codex также поддерживает file-read активацию skill по
 * абсолютному пути location).
 *
 * Значения экранируются (XML entities): & < > " '.
 */
final readonly class FormatSkillCatalogService implements FormatSkillCatalogServiceInterface
{
    private const string HEADER = <<<'TEXT'
        Следующие skills предоставляют специализированные инструкции для конкретных задач.
        Когда задача совпадает с описанием skill — загрузи его SKILL.md инструментом read.
        Относительные пути внутри skill разрешай относительно его каталога (родителя SKILL.md) и используй абсолютные пути в командах.
        TEXT;

    #[Override]
    public function format(array $skills): string
    {
        if ($skills === []) {
            return '';
        }

        $lines = [
            '',
            '',
            self::HEADER,
            '',
            '<available_skills>',
        ];

        foreach ($skills as $skill) {
            $lines[] = '  <skill>';
            $lines[] = '    <name>' . $this->escapeXml($skill->getName()->value()) . '</name>';
            $lines[] = '    <description>' . $this->escapeXml($skill->getDescription()) . '</description>';
            $lines[] = '    <location>' . $this->escapeXml($skill->getLocation()) . '</location>';
            $lines[] = '  </skill>';
        }

        $lines[] = '</available_skills>';

        return implode("\n", $lines);
    }

    private function escapeXml(string $value): string
    {
        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $value,
        );
    }
}
