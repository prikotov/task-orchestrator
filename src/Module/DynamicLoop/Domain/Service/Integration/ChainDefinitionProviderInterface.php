<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Integration;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;

/**
 * Integration-интерфейс для загрузки конфигурации dynamic-цикла из ChainDefinition.
 *
 * Реализация (DynamicLoopDefinitionMapper) находится в Integration-слое
 * и маппит ChainDefinition VO → DynamicLoop VO.
 */
interface ChainDefinitionProviderInterface
{
    /**
     * Загружает конфигурацию dynamic-цепочки по имени.
     */
    public function loadDynamicChainConfig(string $chainName): DynamicLoopConfigVo;
}
