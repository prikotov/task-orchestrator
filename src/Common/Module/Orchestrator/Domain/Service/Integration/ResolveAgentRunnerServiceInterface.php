<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration;

/**
 * Интеграционный сервис реестра движков AI-агентов для Orchestrator Domain.
 *
 * Реализация маппит VO и делегирует в конкретный движок AI-агента.
 */
interface ResolveAgentRunnerServiceInterface
{
    /**
     * Возвращает движок по имени.
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException
     */
    public function get(string $name): RunAgentServiceInterface;

    /**
     * Возвращает движок по умолчанию (первый зарегистрированный).
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException
     */
    public function getDefault(): RunAgentServiceInterface;

    /**
     * Возвращает список всех зарегистрированных движков.
     *
     * @return array<string, RunAgentServiceInterface>
     */
    public function list(): array;
}
