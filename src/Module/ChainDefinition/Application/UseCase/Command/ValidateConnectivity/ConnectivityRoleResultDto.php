<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum\ConnectivityStatusEnum;

/**
 * DTO результата проверки связности одной роли.
 */
final readonly class ConnectivityRoleResultDto
{
    public function __construct(
        public string $role,
        public ConnectivityStatusEnum $status,
        public ?float $durationSeconds = null,
        public ?string $error = null,
        public ?string $commandPreview = null,
    ) {
    }
}
