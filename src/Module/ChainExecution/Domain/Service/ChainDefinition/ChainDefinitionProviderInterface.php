<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ChainDefinition;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;

/**
 * Контракт загрузки конфигурации цепочки из ChainDefinition.
 *
 * Реализация (ChainExecutionDefinitionMapper) находится в Integration-слое
 * и маппит ChainDefinition VO → Execution VO. Сложность задачи задаётся на CLI/Application
 * границе и передаётся готовым значением: отклонения commands_by_complexity применяются
 * при маппинге, до запуска любого агента. По умолчанию — C3.
 */
interface ChainDefinitionProviderInterface
{
    /**
     * Загружает идентификацию цепочки по имени.
     *
     * Возвращает ChainExecution-собственный VO (без зависимости от ChainDefinition.Domain).
     */
    public function loadChainInfo(string $chainName): ExecutionChainInfoVo;

    /**
     * Загружает конфигурацию static-цепочки по имени.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function loadStaticChainConfig(string $chainName, int $complexity = 3): ExecutionStaticChainConfigVo;

    /**
     * Загружает конфигурацию conditional-цепочки по имени.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function loadConditionalChainConfig(string $chainName, int $complexity = 3): ExecutionConditionalChainConfigVo;
}
