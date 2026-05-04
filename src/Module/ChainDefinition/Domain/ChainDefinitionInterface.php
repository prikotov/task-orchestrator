<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;

/**
 * Общий интерфейс определения цепочки оркестрации.
 *
 * Реализуется специализированными VO: StaticChainDefinitionVo,
 * DynamicChainDefinitionVo, ConditionalChainDefinitionVo.
 *
 * Каждый sub-VO содержит только свои специфичные данные + shared kernel
 * через getSharedDefinition().
 *
 * @see SharedChainDefinitionVo
 * @see docs/adr/008-shared-kernel-contract.md
 */
interface ChainDefinitionInterface
{
    /**
     * Возвращает Shared Kernel — идентификацию цепочки (chain identity).
     *
     * Содержит общие поля: name, description, type, budget, timeout, maxTime, roles.
     */
    public function getSharedDefinition(): SharedChainDefinitionVo;

    /**
     * Возвращает имя цепочки.
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getName().
     */
    public function getName(): string;

    /**
     * Возвращает тип цепочки (static/dynamic/conditional).
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getType().
     */
    public function getType(): ChainTypeEnum;

    /**
     * Является ли цепочка динамической?
     */
    public function isDynamic(): bool;

    /**
     * Является ли цепочка условной (conditional)?
     *
     * Условная цепочка — это цепочка с шагами, имеющими when-выражения.
     */
    public function isConditional(): bool;

    /**
     * Возвращает описание цепочки.
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getDescription().
     */
    public function getDescription(): string;

    /**
     * Возвращает таймаут цепочки в секундах (null = не задан).
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getTimeout().
     */
    public function getTimeout(): ?int;

    /**
     * Возвращает максимальное суммарное время выполнения цепочки в секундах (null = безлимит).
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getMaxTime().
     */
    public function getMaxTime(): ?int;

    /**
     * Возвращает бюджетные ограничения цепочки (null = безлимит).
     *
     * Convenience-метод, делегирует к getSharedDefinition()->getBudget().
     */
    public function getBudget(): ?BudgetVo;

    /**
     * Возвращает политику retry по умолчанию для цепочки.
     */
    public function getDefaultRetryPolicy(): ?ChainRetryPolicyVo;

    /**
     * Возвращает конфигурацию роли или null, если не задана.
     */
    public function getRoleConfig(string $role): ?RoleConfigVo;

    /**
     * Возвращает все per-role конфигурации.
     *
     * @return array<string, RoleConfigVo>
     */
    public function getRoles(): array;
}
