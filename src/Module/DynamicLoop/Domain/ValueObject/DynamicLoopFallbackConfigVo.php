<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Value Object конфигурации fallback для роли в dynamic-цикле.
 *
 * Копия ChainDefinition\FallbackConfigVo, без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopFallbackConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда fallback-агента.
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
