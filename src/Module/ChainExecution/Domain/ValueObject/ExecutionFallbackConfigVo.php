<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Execution VO: конфигурация fallback для роли.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\FallbackConfigVo через Integration-маппер.
 */
final readonly class ExecutionFallbackConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда fallback-агента
     */
    public function __construct(
        private array $command = [],
    ) {
    }

    public function getRunnerName(): ?string
    {
        return $this->command[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }
}
