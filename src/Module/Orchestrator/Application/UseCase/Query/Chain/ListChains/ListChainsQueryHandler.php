<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ListChains;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ChainDefinitionDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;

/**
 * Возвращает список всех определений цепочек.
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class ListChainsQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionDtoMapper $mapper,
    ) {
    }

    public function __invoke(ListChainsQuery $query): ListChainsResult
    {
        if ($query->configPath !== null) {
            $this->chainLoader->overridePath($query->configPath);
        }

        return new ListChainsResult(
            chains: $this->mapper->mapList($this->chainLoader->list()),
        );
    }
}
