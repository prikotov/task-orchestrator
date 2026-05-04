<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainConfigViolationDto;

final readonly class ValidateChainConfigResult
{
    /**
     * @param list<ChainConfigViolationDto> $violations
     * @param list<string> $chainNames
     */
    public function __construct(
        public bool $isValid,
        public array $violations,
        public ?string $validChainName = null,
        public array $chainNames = [],
    ) {
    }
}
