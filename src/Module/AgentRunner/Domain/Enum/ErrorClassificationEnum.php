<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum;

/**
 * Классификация ошибки агента для принятия решения о retry.
 *
 * - fatal     — повтор бессмысленен (process crash, невалидный ключ)
 * - transient — повтор может помочь (timeout, rate limit, обычная ошибка)
 * - unknown   — аномалия (isError при exitCode=0), повторяем консервативно
 */
enum ErrorClassificationEnum: string
{
    case fatal = 'fatal';
    case transient = 'transient';
    case unknown = 'unknown';
}
