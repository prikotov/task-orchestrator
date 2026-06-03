<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity;

/**
 * Command (команда) проверки связности top-level roles из chains.yaml.
 */
final readonly class ValidateConnectivityCommand
{
    public function __construct(
        public ?string $configPath = null,
        public ?string $roleName = null,
        public int $timeout = 30,
        public bool $dryRun = false,
    ) {
    }
}
