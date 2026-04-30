<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;

/**
 * Shared Kernel Value Object — идентификация цепочки (chain identity).
 *
 * Immutable VO, содержит поля, общие для всех стратегий (static, dynamic и будущих).
 * Выделяется из ChainDefinitionVo по ADR-008 (Shared Kernel Contract).
 *
 * Поля shared-ядра:
 * - name — имя цепочки
 * - description — описание цепочки
 * - type — тип цепочки (static/dynamic)
 * - budget — бюджетные ограничения (null = безлимит)
 * - timeout — таймаут цепочки в секундах (null = не задан)
 * - maxTime — максимальное суммарное время выполнения в секундах (null = безлимит)
 * - roles — per-role конфигурация (key = role name)
 *
 * @see docs/adr/008-shared-kernel-contract.md
 */
final readonly class SharedChainDefinitionVo
{
    /**
     * @param string $name имя цепочки
     * @param string $description описание
     * @param ChainTypeEnum $type тип цепочки (static/dynamic)
     * @param BudgetVo|null $budget бюджетные ограничения (null = безлимит)
     * @param int|null $timeout таймаут цепочки в секундах (null = не задан)
     * @param int|null $maxTime максимальное суммарное время выполнения в секундах (null = безлимит)
     * @param array<string, RoleConfigVo> $roles per-role конфигурация (key = role name)
     */
    public function __construct(
        private string $name,
        private string $description,
        private ChainTypeEnum $type,
        private ?BudgetVo $budget,
        private ?int $timeout,
        private ?int $maxTime,
        private array $roles,
    ) {
    }

    /**
     * Возвращает имя цепочки.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает описание цепочки.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Возвращает тип цепочки (static/dynamic).
     */
    public function getType(): ChainTypeEnum
    {
        return $this->type;
    }

    /**
     * Возвращает бюджетные ограничения цепочки (null = безлимит).
     */
    public function getBudget(): ?BudgetVo
    {
        return $this->budget;
    }

    /**
     * Возвращает таймаут цепочки в секундах (null = не задан).
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * Возвращает максимальное суммарное время выполнения цепочки в секундах (null = безлимит).
     */
    public function getMaxTime(): ?int
    {
        return $this->maxTime;
    }

    /**
     * Возвращает все per-role конфигурации.
     *
     * @return array<string, RoleConfigVo>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Возвращает конфигурацию роли или null, если не задана.
     */
    public function getRoleConfig(string $role): ?RoleConfigVo
    {
        return $this->roles[$role] ?? null;
    }

    /**
     * Является ли цепочка динамической?
     */
    public function isDynamic(): bool
    {
        return $this->type === ChainTypeEnum::dynamicType;
    }
}
