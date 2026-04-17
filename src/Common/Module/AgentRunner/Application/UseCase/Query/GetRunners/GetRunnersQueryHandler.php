<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;

/**
 * UseCase получения списка доступных движков AI-агентов.
 *
 * Возвращает список RunnerDto (примитивы) через Application-слой.
 *
 * @return list<RunnerDto>
 */
final readonly class GetRunnersQueryHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $registry,
    ) {
    }

    /**
     * @return list<RunnerDto>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(GetRunnersQuery $_query): array
    {
        $runners = $this->registry->list();
        $result = [];

        foreach ($runners as $name => $runner) {
            $result[] = new RunnerDto(
                name: $name,
                isAvailable: $runner->isAvailable(),
            );
        }

        return $result;
    }
}
