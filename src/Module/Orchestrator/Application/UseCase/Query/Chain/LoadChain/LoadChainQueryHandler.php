<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderServiceInterface;

/**
 * Загружает определение цепочки по имени.
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class LoadChainQueryHandler
{
    public function __construct(
        private ChainProviderServiceInterface $chainProvider,
    ) {
    }

    public function __invoke(LoadChainQuery $query): LoadChainResult
    {
        if ($query->configPath !== null) {
            $this->chainProvider->overridePath($query->configPath);
        }

        return new LoadChainResult(
            chain: $this->chainProvider->load($query->chainName),
        );
    }
}
