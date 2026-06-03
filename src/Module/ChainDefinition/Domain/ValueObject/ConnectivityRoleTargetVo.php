<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Target (цель) проверки связности роли из top-level `roles` в chains.yaml.
 */
final readonly class ConnectivityRoleTargetVo
{
    /**
     * @param list<string> $command argv-команда запуска роли
     */
    public function __construct(
        private string $roleName,
        private array $command,
    ) {
        if (trim($this->roleName) === '') {
            throw new InvalidArgumentException('Connectivity role name must not be empty.');
        }

        if ($this->command === []) {
            throw new InvalidArgumentException(sprintf('Role "%s" must define non-empty command.', $this->roleName));
        }

        $command = $this->command;
        $executable = reset($command);
        if ($executable === '') {
            throw new InvalidArgumentException(sprintf('Role "%s" command executable must not be empty.', $this->roleName));
        }
    }

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }
}
