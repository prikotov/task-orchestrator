<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ListChains;


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
