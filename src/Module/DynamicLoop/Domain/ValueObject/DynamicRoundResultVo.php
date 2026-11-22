<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Результат одного раунда dynamic-цикла: метрики, роль, статус.
 *
 * Domain-аналог Application DTO DynamicRoundResultDto.
 * Содержит только primitives — без Application зависимостей.
 *
 * Модельные поля (model/provider/thinking) и кэш-токены заполняются, если раннер
 * сообщил их; для восстановленных раундов старых сессий они равны null/0
 * (отчёт рендерит «н/д»).
 */
final readonly class DynamicRoundResultVo
{
    public function __construct(
        public int $round,
        public string $role,
        public bool $isFacilitator,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $duration,
        public bool $isError = false,
        public ?string $errorMessage = null,
        public ?string $invocation = null,
        public string $systemPrompt = '',
        public string $userPrompt = '',
        public bool $timedOut = false,
        public ?string $model = null,
        public ?string $provider = null,
        public ?string $thinking = null,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {
    }
}
