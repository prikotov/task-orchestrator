<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Execution VO: конфигурация static-цепочки для выполнения.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo через Integration-маппер.
 * Содержит все данные, необходимые execution-слою, без зависимости от ChainDefinition.Domain.
 */
final readonly class ExecutionStaticChainConfigVo
{
    /**
     * @param string $name имя цепочки
     * @param list<ExecutionStepVo> $steps шаги цепочки
     * @param list<ExecutionFixIterationGroupVo> $fixIterations группы итераций
     * @param ExecutionBudgetVo|null $budget бюджетные ограничения
     * @param int|null $timeout таймаут цепочки (секунды)
     * @param array<string, ExecutionRoleConfigVo> $roles per-role конфигурация
     * @param ExecutionRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию
     */
    public function __construct(
        private string $name,
        private array $steps,
        private array $fixIterations,
        private ?ExecutionBudgetVo $budget,
        private ?int $timeout,
        private array $roles,
        private ?ExecutionRetryPolicyVo $defaultRetryPolicy = null,
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

    /**
     * @return list<ExecutionFixIterationGroupVo> группы итераций
     */
    public function getFixIterations(): array
    {
        return $this->fixIterations;
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

    public function getDefaultRetryPolicy(): ?ExecutionRetryPolicyVo
    {
        return $this->defaultRetryPolicy;
    }

    /**
     * Возвращает конфигурацию роли или null.
     */
    public function getRoleConfig(string $role): ?ExecutionRoleConfigVo
    {
        return $this->roles[$role] ?? null;
    }
}
