<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Интерфейс Codex CLI runner'а (OpenAI).
 */
interface CodexAgentRunnerServiceInterface extends AgentRunnerInterface
{
    /**
     * Строит массив CLI-команды для запуска Codex.
     *
     * @return list<string>
     */
    public function buildCommand(AgentRunRequestVo $request): array;
}
