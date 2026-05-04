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
     */
    public function mapStaticChain(StaticChainDefinitionVo $chain): ExecutionStaticChainConfigVo;

    /**
     * Маппит ConditionalChainDefinitionVo → ExecutionConditionalChainConfigVo.
     */
    public function mapConditionalChain(ConditionalChainDefinitionVo $chain): ExecutionConditionalChainConfigVo;
}
