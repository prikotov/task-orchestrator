<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Value Object конфигурации роли для dynamic-цикла.
 *
 * Копия ChainDefinition\RoleConfigVo, без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopRoleConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда для запуска агента
     * @param int|null $timeout таймаут в секундах
     * @param string|null $promptFile путь к файлу описания роли
     * @param DynamicLoopFallbackConfigVo|null $fallback конфигурация fallback runner'а
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private ?DynamicLoopFallbackConfigVo $fallback = null,
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

    public function getFallback(): ?DynamicLoopFallbackConfigVo
    {
        return $this->fallback;
    }
}
