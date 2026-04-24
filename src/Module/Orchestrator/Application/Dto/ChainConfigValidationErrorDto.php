<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Dto;

/**
 * Описание одной ошибки валидации конфигурации цепочки.
 */
final readonly class ChainConfigValidationErrorDto
{
    /**
     * @param string $chainName имя цепочки (или '__global__' для общих ошибок)
     * @param string $message человекочитаемое описание ошибки
     * @param string|null $field путь к полю (например, 'steps[0].role') или null
     */
    public function __construct(
        public string $chainName,
        public string $message,
        public ?string $field = null,
    ) {
    }
}
