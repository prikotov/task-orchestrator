<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;

/**
 * Integration-интерфейс для загрузки конфигурации цепочки из ChainDefinition.
 *
 * Реализация (ChainExecutionDefinitionMapper) находится в Integration-слое
 * и маппит ChainDefinition VO → Execution VO.
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
     */
    public function loadStaticChainConfig(string $chainName): ExecutionStaticChainConfigVo;

    /**
     * Загружает конфигурацию conditional-цепочки по имени.
     */
    public function loadConditionalChainConfig(string $chainName): ExecutionConditionalChainConfigVo;
}
