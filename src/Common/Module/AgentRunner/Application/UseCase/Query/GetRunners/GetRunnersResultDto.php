<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

/**
 * DTO результата запроса списка runner'ов.
 */
final readonly class GetRunnersResultDto
{
    /**
     * @param RunnerDto[] $runners
     */
    public function __construct(
        public array $runners,
    ) {
    }
}
