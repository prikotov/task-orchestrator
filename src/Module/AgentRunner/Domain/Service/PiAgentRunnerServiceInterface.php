<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Интерфейс pi CLI runner'а.
 */
interface PiAgentRunnerServiceInterface extends AgentRunnerInterface
{
    /**
     * Строит массив CLI-команды для запуска pi.
     *
     * @return list<string>
     */
    public function buildCommand(AgentRunRequestVo $request): array;
}
