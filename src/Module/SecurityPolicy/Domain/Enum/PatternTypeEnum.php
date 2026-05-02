<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum;

/**
 * Тип паттерна матчинга для RulePattern.
 *
 * exact — точное совпадение (===).
 * glob  — glob-паттерн (fnmatch, поддерживает * и ?).
 * regex — регулярное выражение (preg_match).
 */
enum PatternTypeEnum: string
{
    case exact = 'exact';
    case glob = 'glob';
    case regex = 'regex';
}
