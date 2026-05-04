<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

/**
 * Value Object результата выполнения одного шага conditional-цепочки.
 *
 * Domain VO для передачи результата шага между Integration и Application слоями.
 * Содержит метрики шага и маркер выполнения.
 */
final readonly class ConditionalStepResultVo
{
    public function __construct(
        public string $role,
        public string $runner,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $duration,
        public bool $isError,
        public ?string $errorMessage = null,
        public bool $passed = true,
        public int $exitCode = 0,
        public string $label = '',
        public bool $timedOut = false,
    ) {
    }
}
