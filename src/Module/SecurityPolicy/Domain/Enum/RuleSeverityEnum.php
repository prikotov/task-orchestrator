<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum;

/**
 * Строгость нарушения правила безопасности.
 *
 * block — блокировка выполнения (выбрасывается исключение).
 * warn  — предупреждение (логирование без блокировки).
 */
enum RuleSeverityEnum: string
{
    case block = 'block';
    case warn = 'warn';
}
