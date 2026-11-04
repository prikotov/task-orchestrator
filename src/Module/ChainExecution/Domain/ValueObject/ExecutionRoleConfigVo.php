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
     * @param array<string, list<string>> $commandsByComplexity
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private array $commandsByComplexity = [],
        private ?string $systemPromptFile = null,
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

    /**
     * @return list<string>
     */
    public function resolveCommand(int $complexity): array
    {
        if ($this->commandsByComplexity === []) {
            return $this->command;
        }

        for ($level = $complexity; $level <= 5; ++$level) {
            if (isset($this->commandsByComplexity['C' . $level])) {
                return $this->commandsByComplexity['C' . $level];
            }
        }

        for ($level = $complexity - 1; $level >= 0; --$level) {
            if (isset($this->commandsByComplexity['C' . $level])) {
                return $this->commandsByComplexity['C' . $level];
            }
        }

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

    public function getSystemPromptFile(): ?string
    {
        return $this->systemPromptFile;
    }

    public function getFallback(): ?ExecutionFallbackConfigVo
    {
        return $this->fallback;
    }
}
