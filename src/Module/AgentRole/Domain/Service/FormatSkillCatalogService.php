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
 *
 * Локаль header'а каталога управляется DI-параметром `task_orchestrator.locale`
 * (env APP_LOCALE). Неизвестная локаль → fallback на `en` (нейтральный default
 * библиотеки; формат pi — англоязычный).
 */
final readonly class FormatSkillCatalogService implements FormatSkillCatalogServiceInterface
{
    /**
     * Карта переводов header'а каталога по локали.
     *
     * en — точная копия формата pi formatSkillsForPrompt (стандартный
     *      англоязычный Agent Skills header); используется как fallback.
     * ru — русский перевод (текущее поведение task-orchestrator как проекта).
     * zh — перевод header'а на китайский (по смыслу ru-версии).
     *
     * Новые локали добавляются сюда расширением карты — без изменения логики.
     */
    private const array HEADERS = [
        'en' => <<<'TEXT'
            The following skills provide specialized instructions for specific tasks.
            When the task matches a skill's description — load its SKILL.md using the read tool.
            Paths inside the skill (scripts/…, references/…) are located next to the SKILL.md, not in the project root.
            Take the directory from <location> and prepend it to the path; in bash, use the absolute path.
            Example: scripts/watch-subagent.sh at <location> /path/run-subagent/SKILL.md → /path/run-subagent/scripts/watch-subagent.sh.
            TEXT,
        'ru' => <<<'TEXT'
            Следующие skills предоставляют специализированные инструкции для конкретных задач.
            Когда задача совпадает с описанием skill — загрузи его SKILL.md инструментом read.
            Пути внутри skill (scripts/…, references/…) лежат рядом с SKILL.md, а не в корне проекта.
            Бери каталог из <location> и подставляй перед путём, в bash выполняй абсолютный путь.
            Пример: scripts/watch-subagent.sh при <location> /path/run-subagent/SKILL.md → /path/run-subagent/scripts/watch-subagent.sh.
            TEXT,
        'zh' => <<<'TEXT'
            以下技能为特定任务提供专门说明。
            当任务与某技能描述相符时 —— 用 read 工具加载该技能的 SKILL.md。
            技能内部路径（scripts/…、references/…）位于 SKILL.md 同级目录下，而非项目根目录。
            从 <location> 取目录并拼接到路径前；在 bash 中使用绝对路径。
            示例：<location> 为 /path/run-subagent/SKILL.md 时，scripts/watch-subagent.sh → /path/run-subagent/scripts/watch-subagent.sh。
            TEXT,
    ];

    private const string DEFAULT_LOCALE = 'en';

    public function __construct(
        private string $locale,
    ) {
    }

    #[Override]
    public function format(array $skills): string
    {
        if ($skills === []) {
            return '';
        }

        $lines = [
            '',
            '',
            $this->header(),
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

    /**
     * Header каталога по текущей локали с fallback на en (default библиотеки).
     *
     * Локаль нормализуется к lower-case: Kernel уже поставляет локаль в нижнем
     * регистре, но это защитная нормализация для симметрии с Locator'ом.
     */
    private function header(): string
    {
        return self::HEADERS[strtolower($this->locale)] ?? self::HEADERS[self::DEFAULT_LOCALE];
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
