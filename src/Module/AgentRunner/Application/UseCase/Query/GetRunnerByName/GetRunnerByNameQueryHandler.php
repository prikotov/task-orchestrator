<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\RunnerDto;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;

/**
 * UseCase получения runner'а по имени или по умолчанию.
 *
 * Инкапсулирует обработку RunnerNotFoundException внутри Application-слоя:
 * если runner не найден — возвращает null вместо выброса исключения.
 */
final readonly class GetRunnerByNameQueryHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $registry,
    ) {
    }

    /**
     * Возвращает RunnerDto или null, если runner не найден.
     *
     * При $query->name === null возвращает runner по умолчанию.
     */
    public function __invoke(GetRunnerByNameQuery $query): ?RunnerDto
    {
        return $this->handle($query);
    }

    /**
     * Возвращает RunnerDto или null, если runner не найден.
     */
    public function handle(GetRunnerByNameQuery $query): ?RunnerDto
    {
        try {
            $runner = $query->name !== null
                ? $this->registry->get($query->name)
                : $this->registry->getDefault();
        } catch (RunnerNotFoundException) {
            return null;
        }

        return new RunnerDto(
            name: $runner->getName(),
            isAvailable: $runner->isAvailable(),
        );
    }
}
