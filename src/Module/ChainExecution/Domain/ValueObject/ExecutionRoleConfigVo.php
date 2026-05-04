<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Execution VO: конфигурация роли для выполнения цепочки.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\RoleConfigVo через Integration-маппер.
 */
final readonly class ExecutionRoleConfigVo
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private ?ExecutionFallbackConfigVo $fallback = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getPromptFile(): ?string
    {
        return $this->promptFile;
    }

    public function getFallback(): ?ExecutionFallbackConfigVo
    {
        return $this->fallback;
    }
}
