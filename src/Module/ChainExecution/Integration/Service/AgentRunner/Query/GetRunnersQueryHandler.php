<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner\Query;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery as AgentRunnerGetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler as AgentRunnerGetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GetRunners\GetRunnersQueryHandlerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GetRunners\RunnerDto;

/**
 * Integration-прокси: делегирует запрос списка AI-движков в AgentRunner.Application.
 *
 * Расположен в Integration-слое, т.к. обращается к чужому Application (разрешено).
 */
final readonly class GetRunnersQueryHandler implements GetRunnersQueryHandlerInterface
{
    public function __construct(
        private AgentRunnerGetRunnersQueryHandler $getRunnersHandler,
    ) {
    }

    /**
     * @return list<RunnerDto>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    #[Override]
    public function __invoke(GetRunnersQuery $_query): array
    {
        $agentRunnerResult = ($this->getRunnersHandler)(new AgentRunnerGetRunnersQuery());

        return array_values(array_map(
            static fn(\TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\RunnerDto $dto): RunnerDto => new RunnerDto(
                name: $dto->name,
                isAvailable: $dto->isAvailable,
            ),
            $agentRunnerResult->runners,
        ));
    }
}
