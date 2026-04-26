<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ChainDefinitionDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;

/**
 * Загружает определение цепочки по имени.
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class LoadChainQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionDtoMapper $mapper,
    ) {
    }

    public function __invoke(LoadChainQuery $query): LoadChainResult
    {
        if ($query->configPath !== null) {
            $this->chainLoader->overridePath($query->configPath);
        }

        return new LoadChainResult(
            chain: $this->mapper->map($this->chainLoader->load($query->chainName)),
        );
    }
}
