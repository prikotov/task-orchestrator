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
     * @param array<string, list<string>> $commandsByComplexity команды по уровням C0–C5
     * @param DynamicLoopFallbackConfigVo|null $fallback конфигурация fallback runner'а
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private array $commandsByComplexity = [],
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

    /**
     * @return list<string>
     */
    public function resolveCommand(int $complexity): array
    {
        $commands = $this->commandsByComplexity;
        if ($this->command !== [] && !isset($commands['C3'])) {
            $commands['C3'] = $this->command;
        }
        if ($commands === []) {
            return $this->command;
        }

        for ($level = $complexity; $level <= 5; ++$level) {
            if (isset($commands['C' . $level])) {
                return $commands['C' . $level];
            }
        }

        for ($level = $complexity - 1; $level >= 0; --$level) {
            if (isset($commands['C' . $level])) {
                return $commands['C' . $level];
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

    public function getFallback(): ?DynamicLoopFallbackConfigVo
    {
        return $this->fallback;
    }
}
