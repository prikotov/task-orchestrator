<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum;

/**
 * Оператор сравнения в условном выражении (when-expression).
 *
 * Поддерживаемые операторы MVP: == (равенство), != (неравенство).
 */
enum ConditionOperatorEnum: string
{
    case equals = '==';
    case notEquals = '!=';
}
