<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;
use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;

/**
 * Value Object определения static-цепочки оркестрации.
 *
 * Содержит только static-specific данные: steps, fixIterations, defaultRetryPolicy.
 * Общие поля — через getSharedDefinition() (SharedChainDefinitionVo).
 *
 * Immutable, readonly. Реализует ChainDefinitionInterface (ISP).
 *
 * @see ChainDefinitionInterface
 * @see SharedChainDefinitionVo
 */
final readonly class StaticChainDefinitionVo implements ChainDefinitionInterface
{
    /**
     * @param SharedChainDefinitionVo $shared общие поля (name, description, type, budget, timeout, roles)
     * @param list<ChainStepVo> $steps шаги static-цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы итераций фикса
     * @param ChainRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию для шагов
     *
     * @internal Используйте {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory::createFromSteps()} —
     *     фабрика внедряет доменную спецификацию fix-итераций через DI и кидает исключение при нарушении инварианта.
     */
    // phpcs:ignore
    public function __construct(
        private SharedChainDefinitionVo $shared,
        private array $steps,
        private array $fixIterations,
        private ?ChainRetryPolicyVo $defaultRetryPolicy = null,
    ) {
    }

    /**
     * Создаёт static-цепочку с линейными шагами.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     *
     * @deprecated Используйте {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory::createFromSteps()}.
     *     Проверка инварианта ссылочной целостности fix-итераций перенесена в фабрику
     *     (FixIterationsReferenceIntegritySpecification); этот static factory оставлен для BC
     *     и не валидирует fix-итерации.
     */
    public static function createFromSteps(
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

        return new self(
            shared: new SharedChainDefinitionVo(
                name: $name,
                description: $description,
                type: ChainTypeEnum::staticType,
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
    #[Override]
    public function isDynamic(): bool
    {
        return $this->shared->isDynamic();
    }

    /**
     * Является ли цепочка условной (conditional)?
     */
    #[Override]
    public function isConditional(): bool
    {
        return $this->shared->isConditional();
    }

    #[Override]
    public function getDescription(): string
    {
        return $this->shared->getDescription();
    }

    #[Override]
    public function getTimeout(): ?int
    {
        return $this->shared->getTimeout();
    }

    #[Override]
    public function getMaxTime(): ?int
    {
        return $this->shared->getMaxTime();
    }

    #[Override]
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
    #[Override]
    public function getDefaultRetryPolicy(): ?ChainRetryPolicyVo
    {
        return $this->defaultRetryPolicy;
    }

    #[Override]
    public function getRoleConfig(string $role): ?RoleConfigVo
    {
        return $this->shared->getRoleConfig($role);
    }

    /**
     * @return array<string, RoleConfigVo>
     */
    #[Override]
    public function getRoles(): array
    {
        return $this->shared->getRoles();
    }

}
