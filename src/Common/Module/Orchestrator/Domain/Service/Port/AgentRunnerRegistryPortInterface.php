<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port;

/**
 * Port-интерфейс реестра движков AI-агентов для Orchestrator Domain.
 *
 * Реализация маппит VO и делегирует в конкретный движок AI-агента.
 */
interface AgentRunnerRegistryPortInterface
{
    /**
     * Возвращает движок по имени.
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException
     */
    public function get(string $name): AgentRunnerPortInterface;

    /**
     * Возвращает движок по умолчанию (первый зарегистрированный).
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException
     */
    public function getDefault(): AgentRunnerPortInterface;

    /**
     * Возвращает список всех зарегистрированных движков.
     *
     * @return array<string, AgentRunnerPortInterface>
     */
    public function list(): array;
}
