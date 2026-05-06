<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GetRunners;

/**
 * Контракт Query-обработчика списка AI-движков.
 *
 * Presentation-слой зависит от этого интерфейса (Application),
 * а реализация находится в Integration (cross-module delegation).
 */
interface GetRunnersQueryHandlerInterface
{
    /**
     * @return list<RunnerDto>
     */
    public function __invoke(GetRunnersQuery $query): array;
}
