<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use InvalidArgumentException;
use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;

/**
 * Value Object определения conditional-цепочки оркестрации.
 *
 * Содержит только conditional-specific данные: steps (с when-выражениями),
 * fixIterations, defaultRetryPolicy.
 * Общие поля — через getSharedDefinition() (SharedChainDefinitionVo).
 *
 * Conditional-цепочка — статическая цепочка с условным ветвлением шагов.
 * Шаги могут содержать optional ConditionExpressionVo (when-expressions).
 *
 * Immutable, readonly. Реализует ChainDefinitionInterface (ISP).
 *
 * @see ChainDefinitionInterface
 * @see SharedChainDefinitionVo
 */
final readonly class ConditionalChainDefinitionVo implements ChainDefinitionInterface
{
    /**
     * @param SharedChainDefinitionVo $shared общие поля (name, description, type, budget, timeout, roles)
     * @param list<ChainStepVo> $steps шаги conditional-цепочки (могут содержать when-выражения)
     * @param list<FixIterationGroupVo> $fixIterations группы итераций фикса
     * @param ChainRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию для шагов
     */
    public function __construct(
        private SharedChainDefinitionVo $shared,
        private array $steps,
        private array $fixIterations,
        private ?ChainRetryPolicyVo $defaultRetryPolicy = null,
    ) {
    }

    /**
     * Создаёт conditional-цепочку — статическую цепочку с условным ветвлением шагов.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     */
    public static function create(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): self {
        if (count($steps) === 0) {
            throw new InvalidArgumentException(
                sprintf('Chain "%s" must have at least one step.', $name),
            );
        }

        self::validateFixIterations($name, $steps, $fixIterations);

        return new self(
            shared: new SharedChainDefinitionVo(
                name: $name,
                description: $description,
                type: ChainTypeEnum::conditionalType,
                budget: $budget,
                timeout: $timeout,
                maxTime: null,
                roles: $roles,
            ),
            steps: $steps,
            fixIterations: $fixIterations,
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    #[Override]
    public function getSharedDefinition(): SharedChainDefinitionVo
    {
        return $this->shared;
    }

    #[Override]
    public function getName(): string
    {
        return $this->shared->getName();
    }

    #[Override]
    public function getType(): ChainTypeEnum
    {
        return $this->shared->getType();
    }

    /**
     * Является ли цепочка динамической?
     */
    #[\Override]
    public function isDynamic(): bool
    {
        return $this->shared->isDynamic();
    }

    /**
     * Является ли цепочка условной (conditional)?
     */
    #[\Override]
    public function isConditional(): bool
    {
        return $this->shared->isConditional();
    }

    #[\Override]
    public function getDescription(): string
    {
        return $this->shared->getDescription();
    }

    #[\Override]
    public function getTimeout(): ?int
    {
        return $this->shared->getTimeout();
    }

    #[\Override]
    public function getMaxTime(): ?int
    {
        return $this->shared->getMaxTime();
    }

    #[\Override]
    public function getBudget(): ?BudgetVo
    {
        return $this->shared->getBudget();
    }

    /**
     * @return list<ChainStepVo>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<FixIterationGroupVo>
     */
    public function getFixIterations(): array
    {
        return $this->fixIterations;
    }

    /**
     * Возвращает политику retry по умолчанию для цепочки.
     */
    #[\Override]
    public function getDefaultRetryPolicy(): ?ChainRetryPolicyVo
    {
        return $this->defaultRetryPolicy;
    }

    #[\Override]
    public function getRoleConfig(string $role): ?RoleConfigVo
    {
        return $this->shared->getRoleConfig($role);
    }

    /**
     * @return array<string, RoleConfigVo>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->shared->getRoles();
    }

    /**
     * Валидирует fix_iterations: все stepNames должны существовать среди шагов,
     * имена шагов в группе не должны пересекаться между группами.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    private static function validateFixIterations(string $name, array $steps, array $fixIterations): void
    {
        if ($fixIterations === []) {
            return;
        }

        $nameMap = [];
        foreach ($steps as $index => $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = $index;
            }
        }

        $allGroupStepNames = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                if (!isset($nameMap[$stepName])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Chain "%s": fix iteration group "%s" references unknown step name "%s".',
                            $name,
                            $group->getGroup(),
                            $stepName,
                        ),
                    );
                }

                if (isset($allGroupStepNames[$stepName])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Chain "%s": step name "%s" belongs to multiple fix iteration groups ("%s" and "%s").',
                            $name,
                            $stepName,
                            $allGroupStepNames[$stepName],
                            $group->getGroup(),
                        ),
                    );
                }

                $allGroupStepNames[$stepName] = $group->getGroup();
            }
        }
    }
}
