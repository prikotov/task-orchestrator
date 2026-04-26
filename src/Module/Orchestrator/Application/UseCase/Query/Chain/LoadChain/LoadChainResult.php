<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;

final readonly class LoadChainResult
{
    public function __construct(
        public ChainDefinitionDto $chain,
    ) {
    }
}
