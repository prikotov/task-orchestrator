<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\RunRoleStartupCheck;

/**
 * DTO результата проверки запуска ролей из chains.yaml.
 */
final readonly class RunRoleStartupCheckResultDto
{
    /**
     * @param list<ConnectivityRoleResultDto> $results
     */
    public function __construct(
        public array $results,
        public bool $hasFailures,
        public bool $dryRun,
    ) {
    }
}
