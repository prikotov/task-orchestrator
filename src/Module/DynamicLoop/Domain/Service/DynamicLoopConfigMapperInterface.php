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
     */
    public function map(DynamicChainDefinitionVo $chain): DynamicLoopConfigVo;
}
