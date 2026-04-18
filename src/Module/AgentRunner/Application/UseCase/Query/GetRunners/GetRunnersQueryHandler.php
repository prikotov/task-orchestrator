<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;

/**
 * UseCase получения списка доступных движков AI-агентов.
 *
 * Возвращает GetRunnersResultDto через Application-слой.
 */
final readonly class GetRunnersQueryHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $registry,
    ) {
    }

    /**
     * Вызывает handler через __invoke (MessageHandler pattern).
     */
    public function __invoke(GetRunnersQuery $query): GetRunnersResultDto
    {
        return $this->handle($query);
    }

    /**
     * Возвращает список доступных runner'ов.
     */
    public function handle(GetRunnersQuery $query): GetRunnersResultDto
    {
        $runners = [];
        foreach ($this->registry->list() as $name => $runner) {
            if ($query->filterName !== null && $name !== $query->filterName) {
                continue;
            }

            $runners[] = new RunnerDto(
                name: $runner->getName(),
                isAvailable: $runner->isAvailable(),
            );
        }

        return new GetRunnersResultDto($runners);
    }
}
