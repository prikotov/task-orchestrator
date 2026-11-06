<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\ChainDefinition;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;

/**
 * Контракт загрузки конфигурации dynamic-цикла из ChainDefinition.
 *
 * Реализация (DynamicLoopDefinitionMapper) находится в Integration-слое
 * и маппит ChainDefinition VO → DynamicLoop VO. Сложность задачи задаётся на
 * CLI/Application границе и передаётся готовым значением уровнем 0–5 (C0–C5):
 * отклонения commands_by_complexity применяются при маппинге, до запуска
 * любого агента. По умолчанию — C3. Параметр --topic на сложность не влияет.
 */
interface ChainDefinitionProviderInterface
{
    /**
     * Загружает конфигурацию dynamic-цепочки по имени.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function loadDynamicChainConfig(string $chainName, int $complexity = 3): DynamicLoopConfigVo;
}
