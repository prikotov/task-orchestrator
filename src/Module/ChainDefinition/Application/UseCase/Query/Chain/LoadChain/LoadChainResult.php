<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadChain;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainDefinitionDto;

final readonly class LoadChainResult
{
    public function __construct(
        public ChainDefinitionDto $chain,
    ) {
    }
}
