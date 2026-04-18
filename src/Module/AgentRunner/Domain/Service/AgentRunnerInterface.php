<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Контракт движка AI-агента.
 *
 * Реализации инкапсулируют specifics CLI-инструмента (pi, Codex CLI и др.).
 */
interface AgentRunnerInterface
{
    /**
     * Уникальное имя движка (например, 'pi', 'codex').
     */
    public function getName(): string;

    /**
     * Проверяет доступность движка в текущем окружении.
     */
    public function isAvailable(): bool;

    /**
     * Запускает агент с заданным запросом.
     */
    public function run(AgentRunRequestVo $request): AgentResultVo;
}
