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
        public string $name,
        public array $steps,
        public ?ExecutionBudgetVo $budget,
        public ?int $timeout,
        public array $roles,
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
