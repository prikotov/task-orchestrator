<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

/**
 * DTO запроса списка доступных runner'ов.
 */
final readonly class GetRunnersQuery
{
    public function __construct(
        public ?string $filterName = null,
    ) {
    }
}
