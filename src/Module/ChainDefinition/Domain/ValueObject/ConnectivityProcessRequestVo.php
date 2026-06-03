<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Request (запрос) на запуск процесса проверки связности роли.
 */
final readonly class ConnectivityProcessRequestVo
{
    /**
     * @param list<string> $command argv-команда запуска роли
     */
    public function __construct(
        private string $roleName,
        private array $command,
        private int $timeout,
        private ?string $stdinPrompt = null,
    ) {
        if (trim($this->roleName) === '') {
            throw new InvalidArgumentException('Connectivity process role name must not be empty.');
        }

        if ($this->command === []) {
            throw new InvalidArgumentException(sprintf('Role "%s" process command must not be empty.', $this->roleName));
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException(sprintf('Role "%s" process timeout must be positive.', $this->roleName));
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

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getStdinPrompt(): ?string
    {
        return $this->stdinPrompt;
    }
}
