<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Execution VO: конфигурация conditional-цепочки для выполнения.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo через Integration-маппер.
 */
final readonly class ExecutionConditionalChainConfigVo
{
    /**
     * @param string $name имя цепочки
     * @param list<ExecutionStepVo> $steps шаги цепочки
     * @param ExecutionBudgetVo|null $budget бюджетные ограничения
     * @param int|null $timeout таймаут цепочки (секунды)
     * @param array<string, ExecutionRoleConfigVo> $roles per-role конфигурация
     */
    public function __construct(
        private string $name,
        private array $steps,
        private ?ExecutionBudgetVo $budget,
        private ?int $timeout,
        private array $roles,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<ExecutionStepVo> шаги цепочки
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getBudget(): ?ExecutionBudgetVo
    {
        return $this->budget;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * @return array<string, ExecutionRoleConfigVo> per-role конфигурация
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Возвращает конфигурацию роли или null.
     */
    public function getRoleConfig(string $role): ?ExecutionRoleConfigVo
    {
        return $this->roles[$role] ?? null;
    }
}
