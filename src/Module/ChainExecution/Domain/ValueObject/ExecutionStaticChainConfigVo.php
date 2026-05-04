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
        public string $name,
        public array $steps,
        public array $fixIterations,
        public ?ExecutionBudgetVo $budget,
        public ?int $timeout,
        public array $roles,
        public ?ExecutionRetryPolicyVo $defaultRetryPolicy = null,
    ) {
    }

    /**
     * Возвращает конфигурацию роли или null.
     */
    public function getRoleConfig(string $role): ?ExecutionRoleConfigVo
    {
        return $this->roles[$role] ?? null;
    }
}
