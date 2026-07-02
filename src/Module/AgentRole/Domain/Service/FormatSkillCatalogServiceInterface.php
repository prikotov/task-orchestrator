<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;

/**
 * Форматирует каталог skills для включения в system prompt агента.
 *
 * Доменный контракт: принимает упорядоченный список skills и возвращает
 * текстовый блок каталога в формате, совместимом со стандартом Agent Skills
 * (https://agentskills.io) и нативным выводом pi (`formatSkillsForPrompt`):
 * XML-блок `<available_skills>` с полями name/description/location.
 *
 * Прогрессивная загрузка (progressive disclosure): в system prompt попадает
 * только каталог (имя + описание + путь, ~50-100 токенов на skill); полное
 * тело SKILL.md агент читает по требованию через read.
 */
interface FormatSkillCatalogServiceInterface
{
    /**
     * @param list<SkillMetadataVo> $skills упорядоченный список skills роли
     *
     * @return string каталог skills для system prompt; пустая строка, если список пуст
     *                (по стандарту пустой блок не выводится, чтобы не путать модель)
     */
    public function format(array $skills): string;
}
