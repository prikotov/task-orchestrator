<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity;

/**
 * DTO результата проверки связности ролей.
 */
final readonly class ValidateConnectivityResultDto
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
