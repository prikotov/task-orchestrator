<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Dto;

/**
 * Результат валидации конфигурации цепочек.
 *
 * Application-DTO: используется ChainConfigValidator для возврата
 * структурированного результата в Presentation-слой (OrchestrateCommand).
 */
final readonly class ChainConfigValidationResultDto
{
    /**
     * @param bool $isValid признак валидности конфигурации
     * @param list<ChainConfigValidationErrorDto> $errors список ошибок (пустой при валидном конфиге)
     * @param list<string> $validatedChains имена провалидированных цепочек (для вывода)
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
        public array $validatedChains = [],
    ) {
    }
}
