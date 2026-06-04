<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Resolved command (разрешённая команда) проверки запуска ролей из chains.yaml с production-like argv.
 */
final readonly class ConnectivityResolvedCommandVo
{
    /**
     * @param list<string> $command argv-команда с разрешёнными placeholders (плейсхолдерами)
     * @param list<string> $cleanupPaths временные файлы для удаления после preview/run
     */
    public function __construct(
        private string $roleName,
        private array $command,
        private array $cleanupPaths = [],
    ) {
        if (trim($this->roleName) === '') {
            throw new InvalidArgumentException('Connectivity resolved command role name must not be empty.');
        }

        if ($this->command === []) {
            throw new InvalidArgumentException(sprintf('Role "%s" resolved command must not be empty.', $this->roleName));
        }

        $executable = $this->command[0];
        if ($executable === '') {
            throw new InvalidArgumentException(sprintf('Role "%s" resolved command executable must not be empty.', $this->roleName));
        }

        foreach ($this->cleanupPaths as $path) {
            if ($path === '') {
                throw new InvalidArgumentException(sprintf('Role "%s" cleanup path must not be empty.', $this->roleName));
            }
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

    /**
     * @return list<string>
     */
    public function getCleanupPaths(): array
    {
        return $this->cleanupPaths;
    }
}
