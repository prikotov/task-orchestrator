<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Enum;

/**
 * Тип шага цепочки оркестрации.
 *
 * agent — выполнение AI-агентом в определённой роли (недетерминированный).
 * quality_gate — выполнение shell-команды для проверки (детерминированный: pass/fail).
 */
enum ChainStepTypeEnum: string
{
    case agent = 'agent';
    case qualityGate = 'quality_gate';
}
