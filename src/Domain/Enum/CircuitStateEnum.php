<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Enum;

/**
 * Состояния Circuit Breaker для Agent Runner.
 *
 * Три состояния по паттерну Martin Fowler:
 * - closed   — нормальная работа, вызовы пропускаются
 * - open     — вызовы блокируются, runner считается неработающим
 * - halfOpen — пробный вызов для проверки восстановления runner'а
 */
enum CircuitStateEnum: string
{
    case closed = 'closed';
    case open = 'open';
    case halfOpen = 'half_open';
}
