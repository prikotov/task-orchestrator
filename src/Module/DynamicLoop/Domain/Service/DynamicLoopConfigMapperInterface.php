<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;

/**
 * Контракт маппинга DynamicChainDefinitionVo → DynamicLoopConfigVo.
 *
 * Реализация (DynamicLoopDefinitionMapper) находится в Integration-слое.
 * DynamicExecutionStrategy зависит от этого интерфейса, а не от конкретного маппера.
 */
interface DynamicLoopConfigMapperInterface
{
    /**
     * Маппит DynamicChainDefinitionVo → DynamicLoopConfigVo.
     *
     * @param int $complexity готовая сложность задачи уровнем 0–5 (C0–C5):
     *        отклонения commands_by_complexity применяются к командам ролей при маппинге
     *        (по умолчанию C3; дефолт подставляет CLI-граница agent:orchestrate).
     */
    public function map(DynamicChainDefinitionVo $chain, int $complexity = 3): DynamicLoopConfigVo;
}
