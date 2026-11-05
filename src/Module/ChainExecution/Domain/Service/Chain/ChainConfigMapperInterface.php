<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;

/**
 * Контракт маппинга ChainDefinition VO → Execution VO.
 *
 * Реализация (ChainExecutionDefinitionMapper) находится в Integration-слое.
 * Стратегии выполнения зависят от этого интерфейса, а не от конкретного маппера.
 */
interface ChainConfigMapperInterface
{
    /**
     * Маппит StaticChainDefinitionVo → ExecutionStaticChainConfigVo.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function mapStaticChain(StaticChainDefinitionVo $chain, int $complexity = 3): ExecutionStaticChainConfigVo;

    /**
     * Маппит ConditionalChainDefinitionVo → ExecutionConditionalChainConfigVo.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function mapConditionalChain(ConditionalChainDefinitionVo $chain, int $complexity = 3): ExecutionConditionalChainConfigVo;
}
