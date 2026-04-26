<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ListChains;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;

/**
 * @see ListChainsQueryHandler
 */
final readonly class ListChainsQuery
{
    public function __construct(
        public ?string $configPath = null,
    ) {
    }
}
