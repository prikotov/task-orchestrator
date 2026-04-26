<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;

/**
 * @see LoadChainQueryHandler
 */
final readonly class LoadChainQuery
{
    public function __construct(
        public string $chainName,
        public ?string $configPath = null,
    ) {
    }
}
