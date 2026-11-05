<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Value Object конфигурации роли для dynamic-цикла.
 *
 * Копия ChainDefinition\RoleConfigVo, без зависимости от ChainDefinition.Domain.
 * $command содержит уже выбранную по сложности задачи команду
 * (RoleConfigVo::resolveCommandByComplexity()), карта отклонений сохраняется как есть.
 */
final readonly class DynamicLoopRoleConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда для запуска агента
     * @param int|null $timeout таймаут в секундах
     * @param string|null $promptFile путь к файлу описания роли
     * @param array<string, list<string>> $commandsByComplexity отклонения команды по уровням сложности C0–C5
     *        (полные CLI-команды; профили резолвятся при загрузке YAML). Команда для текущей
     *        сложности уже выбрана в $command; карта сохраняется для инспекции и обратной
     *        совместимости публичного контракта 0.11.x.
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
     * Возвращает отклонения команды по уровням сложности задачи.
     *
     * @return array<string, list<string>> уровень C0–C5 → полная CLI-команда уровня.
     */
    public function getCommandsByComplexity(): array
    {
        return $this->commandsByComplexity;
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
