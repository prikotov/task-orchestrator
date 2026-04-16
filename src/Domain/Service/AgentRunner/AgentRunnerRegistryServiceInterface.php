<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\AgentRunner;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;

/**
 * Интерфейс реестра движков AI-агентов.
 */
interface AgentRunnerRegistryServiceInterface
{
    /**
     * Возвращает движок по имени.
     *
     * @throws \TasK\Orchestrator\Domain\Exception\RunnerNotFoundException
     */
    public function get(string $name): AgentRunnerInterface;

    /**
     * Возвращает движок по умолчанию (первый зарегистрированный).
     *
     * @throws \TasK\Orchestrator\Domain\Exception\RunnerNotFoundException
     */
    public function getDefault(): AgentRunnerInterface;

    /**
     * Возвращает список всех зарегистрированных движков.
     *
     * @return array<string, AgentRunnerInterface>
     */
    public function list(): array;
}
