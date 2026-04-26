<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Enum;

/**
 * Типизированные exit codes CLI-команды orchestrate.
 *
 * Контракт Presentation ↔ Application: AI-агент (и CI-скрипты) получает
 * конкретный код — не «что-то сломалось», а «chain не найден» / «budget exceeded» / «невалидный конфиг».
 */
enum OrchestrateExitCodeEnum: int
{
    /** Успешное завершение цепочки. */
    case success = 0;

    /** Ошибка выполнения шага/агента (static/dynamic). */
    case chainFailed = 1;

    /** Запрошенная цепочка не найдена в конфигурации. */
    case chainNotFound = 3;

    /** Превышен бюджет цепочки. */
    case budgetExceeded = 4;

    /** Неверная конфигурация цепочки или аргументы команды. */
    case invalidConfig = 5;

    /**
     * Превышен таймаут.
     *
     * Возвращается, когда шаг цепочки или dynamic-раунд завершился
     * по таймауту Symfony Process.
     * Флаг timedOut propagate'ится через AgentResultVo → ChainRunResultVo → StepResultDto/OrchestrateChainResultDto.
     */
    case timeout = 6;
}
