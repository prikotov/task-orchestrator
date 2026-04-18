<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;

/**
 * Интерфейс реестра движков AI-агентов.
 */
interface AgentRunnerRegistryServiceInterface
{
    /**
     * Возвращает движок по имени.
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RunnerNotFoundException
     */
    public function get(string $name): AgentRunnerInterface;

    /**
     * Возвращает движок по умолчанию (первый зарегистрированный).
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RunnerNotFoundException
     */
    public function getDefault(): AgentRunnerInterface;

    /**
     * Возвращает список всех зарегистрированных движков.
     *
     * @return array<string, AgentRunnerInterface>
     */
    public function list(): array;
}
