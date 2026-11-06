<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Execution VO: конфигурация роли для выполнения цепочки.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\RoleConfigVo через Integration-маппер:
 * $command содержит уже выбранную по сложности задачи команду
 * (RoleConfigVo::resolveCommandByComplexity()), карта отклонений сохраняется как есть.
 */
final readonly class ExecutionRoleConfigVo
{
    /**
     * @param list<string> $command
     * @param array<string, list<string>> $commandsByComplexity отклонения команды по уровням сложности C0–C5
     *        (полные CLI-команды; профили резолвятся при загрузке YAML). Команда для текущей
     *        сложности уже выбрана в $command; карта сохраняется для инспекции и обратной
     *        совместимости публичного контракта 0.11.x.
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

    public function getSystemPromptFile(): ?string
    {
        return $this->systemPromptFile;
    }

    public function getFallback(): ?ExecutionFallbackConfigVo
    {
        return $this->fallback;
    }
}
