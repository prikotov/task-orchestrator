<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\RunRoleStartupCheck;

/**
 * Command (команда) проверки запуска ролей из chains.yaml.
 */
final readonly class RunRoleStartupCheckCommand
{
    public function __construct(
        public ?string $configPath = null,
        public ?string $roleName = null,
        public int $timeout = 30,
        public bool $dryRun = false,
    ) {
    }
}
