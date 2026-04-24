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
     * @todo 2026-04-24: timeout case определён, но пока не возвращается — ProcessTimedOutException
     *       обрабатывается внутри PiAgentRunner и не доходит до Command.
     *       Для корректного возврата timeout (6) нужно реализовать propagation timeout-информации
     *       через OrchestrateChainResultDto или отдельное Domain-исключение.
     */
    case timeout = 6;
}
