<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ListChains;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainDefinitionDto;

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
