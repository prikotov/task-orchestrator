<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ListChains;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;

final readonly class ListChainsResult
{
    /**
     * @param array<string, ChainDefinitionDto> $chains
     */
    public function __construct(
        public array $chains,
    ) {
    }
}
