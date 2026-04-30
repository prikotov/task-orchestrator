<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig;


/**
 * @see ValidateChainConfigQueryHandler
 */
final readonly class ValidateChainConfigQuery
{
    /**
     * @param string|null $chainName Имя цепочки или null для валидации всех
     */
    public function __construct(
        public ?string $chainName = null,
        public ?string $configPath = null,
    ) {
    }
}
