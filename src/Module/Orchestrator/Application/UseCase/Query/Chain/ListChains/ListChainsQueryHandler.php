<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ListChains;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderServiceInterface;

/**
 * Возвращает список всех определений цепочек.
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class ListChainsQueryHandler
{
    public function __construct(
        private ChainProviderServiceInterface $chainProvider,
    ) {
    }

    public function __invoke(ListChainsQuery $query): ListChainsResult
    {
        if ($query->configPath !== null) {
            $this->chainProvider->overridePath($query->configPath);
        }

        return new ListChainsResult(
            chains: $this->chainProvider->list(),
        );
    }
}
