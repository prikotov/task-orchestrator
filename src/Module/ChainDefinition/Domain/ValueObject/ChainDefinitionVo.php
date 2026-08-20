<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;
use LogicException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;

/**
 * Value Object определения цепочки оркестрации.
 *
 * Внутреннее состояние сгруппировано в специализированные sub-VO
 * (SharedChainDefinitionVo + PromptConfigurationVo) по ADR-008;
 * публичный API фасада не изменился.
 *
 * @deprecated Используйте {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory}
 *     со специализированными sub-VO:
 *     - StaticChainDefinitionVo для static-цепочек
 *     - DynamicChainDefinitionVo для dynamic-цепочек
 *     - ConditionalChainDefinitionVo для conditional-цепочек
 * Все три реализуют ChainDefinitionInterface.
 * Будет удалён в следующем мажорном релизе.
 *
 * @see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface
 * @see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo
 * @see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo
 * @see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo
 */
final readonly class ChainDefinitionVo
{
    /**
     * @param SharedChainDefinitionVo $shared идентификация цепочки (name, description, type, budget, timeout, maxTime, roles)
     * @param list<ChainStepVo> $steps шаги для static-цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы итераций фикса
     * @param string|null $facilitator роль фасилитатора (для dynamic)
     * @param list<string> $participants роли участников (для dynamic)
     * @param int $maxRounds лимит раундов (для dynamic)
     * @param PromptConfigurationVo|null $promptConfiguration конфигурация промптов (для dynamic)
     * @param ChainRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию для шагов цепочки
     */
    private function __construct(
        private SharedChainDefinitionVo $shared,
        private array $steps,
        private array $fixIterations,
        private ?string $facilitator,
        private array $participants,
        private int $maxRounds,
        private ?PromptConfigurationVo $promptConfiguration,
        private ?ChainRetryPolicyVo $defaultRetryPolicy,
    ) {
        if (!$this->areFixIterationsReferencesValid($this->steps, $this->fixIterations)) {
            throw new InvalidArgumentException(sprintf(
                'Chain "%s" has invalid fix-iterations references (unknown step or step in multiple groups).',
                $this->shared->getName(),
            ));
        }
    }

    /**
     * Проверяет ссылочную целостность групп fix-итераций (deprecated inline-проверка).
     *
     * Восстанавливает поведение, существовавшее до PR #261, в виде чистого предиката;
     * выброс исключения с generic-сообщением выполняет приватный конструктор.
     * Каждое имя шага из групп fix-итераций должно существовать среди именованных
     * шагов (ChainStepVo с непустым name) и не принадлежать нескольким группам.
     *
     * Не зависит от FixIterationsReferenceIntegritySpecification: правило Deptrac
     * DomainVo ↛ DomainSpecification запрещает VO обращаться к specification.
     *
     * @param list<ChainStepVo> $steps шаги цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы fix-итераций
     *
     * @return bool true — если fixIterations пуст, либо каждое имя шага из групп
     *     существует среди именованных шагов и не принадлежит нескольким группам
     */
    private function areFixIterationsReferencesValid(array $steps, array $fixIterations): bool
    {
        if ($fixIterations === []) {
            return true;
        }

        $nameMap = [];
        foreach ($steps as $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = true;
            }
        }

        $seenStepNames = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                if (!isset($nameMap[$stepName]) || isset($seenStepNames[$stepName])) {
                    return false;
                }

                $seenStepNames[$stepName] = true;
            }
        }

        return true;
    }

    /**
     * Создаёт static-цепочку с линейными шагами.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
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
        return self::createLinearChain(
            name: $name,
            description: $description,
            steps: $steps,
            fixIterations: $fixIterations,
            roles: $roles,
            defaultRetryPolicy: $defaultRetryPolicy,
            budget: $budget,
            timeout: $timeout,
            type: ChainTypeEnum::staticType,
        );
    }

    /**
     * Создаёт conditional-цепочку — статическую цепочку с условным ветвлением шагов.
     *
     * Шаги могут содержать optional ConditionExpressionVo (when-expressions).
     * Цепочки без when на шагах остаются static, но если YAML содержит when:,
     * загрузчик автоматически переключает тип на conditional.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     */
    public static function createFromConditionalSteps(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): self {
        return self::createLinearChain(
            name: $name,
            description: $description,
            steps: $steps,
            fixIterations: $fixIterations,
            roles: $roles,
            defaultRetryPolicy: $defaultRetryPolicy,
            budget: $budget,
            timeout: $timeout,
            type: ChainTypeEnum::conditionalType,
        );
    }

    /**
     * Общая реализация создания static/conditional-цепочки с линейными шагами.
     *
     * Валидирует шаги и fix-итерации, затем создаёт VO с заданным типом цепочки.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     */
    private static function createLinearChain(
        string $name,
        string $description,
        array $steps,
        array $fixIterations,
        array $roles,
        ?ChainRetryPolicyVo $defaultRetryPolicy,
        ?BudgetVo $budget,
        ?int $timeout,
        ChainTypeEnum $type,
    ): self {
        if (count($steps) === 0) {
            throw new InvalidArgumentException(
                sprintf('Chain "%s" must have at least one step.', $name),
            );
        }

        // Guard ссылочной целостности fix-итераций выполняется в приватном конструкторе
        // (единая точка для всех фабрик): DomainVo не может зависеть от DomainSpecification
        // (правило Deptrac); detailed-валидация остаётся в ChainDefinitionFactory.

        return new self(
            shared: new SharedChainDefinitionVo(
                name: $name,
                description: $description,
                type: $type,
                budget: $budget,
                timeout: $timeout,
                maxTime: null,
                roles: $roles,
            ),
            steps: $steps,
            fixIterations: $fixIterations,
            facilitator: null,
            participants: [],
            maxRounds: 10,
            promptConfiguration: null,
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    /**
     * Создаёт dynamic-цепочку с фасилитатором и участниками.
     *
     * @param list<string> $participants
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     */
    public static function createFromDynamic(
        string $name,
        string $description,
        string $facilitator,
        array $participants,
        int $maxRounds,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
        string $facilitatorFinalizePrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
        ?int $maxTime = null,
    ): self {
        if ($facilitator === '') {
            throw new InvalidArgumentException(
                sprintf('Dynamic chain "%s" must specify a facilitator role.', $name),
            );
        }

        if (count($participants) === 0) {
            throw new InvalidArgumentException(
                sprintf('Dynamic chain "%s" must have at least one participant.', $name),
            );
        }

        if (
            trim($brainstormSystemPrompt) === ''
            || trim($facilitatorAppendPrompt) === ''
            || trim($facilitatorStartPrompt) === ''
            || trim($facilitatorContinuePrompt) === ''
            || trim($facilitatorFinalizePrompt) === ''
            || trim($participantAppendPrompt) === ''
            || trim($participantUserPrompt) === ''
        ) {
            throw new InvalidArgumentException(
                sprintf('Dynamic chain "%s" must have non-empty prompts.', $name),
            );
        }

        return new self(
            shared: new SharedChainDefinitionVo(
                name: $name,
                description: $description,
                type: ChainTypeEnum::dynamicType,
                budget: $budget,
                timeout: $timeout,
                maxTime: $maxTime,
                roles: $roles,
            ),
            steps: [],
            fixIterations: [],
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            promptConfiguration: new PromptConfigurationVo(
                brainstormSystemPrompt: $brainstormSystemPrompt,
                facilitatorAppendPrompt: $facilitatorAppendPrompt,
                facilitatorStartPrompt: $facilitatorStartPrompt,
                facilitatorContinuePrompt: $facilitatorContinuePrompt,
                facilitatorFinalizePrompt: $facilitatorFinalizePrompt,
                participantAppendPrompt: $participantAppendPrompt,
                participantUserPrompt: $participantUserPrompt,
            ),
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    /**
     * Возвращает Shared Kernel — идентификацию цепочки (chain identity).
     *
     * Извлекает общие поля (name, description, type, budget, timeout, maxTime, roles)
     * в immutable SharedChainDefinitionVo по ADR-008.
     */
    public function getSharedDefinition(): SharedChainDefinitionVo
    {
        return $this->shared;
    }

    /**
     * @deprecated Use $chain->getSharedDefinition()->getName() instead. Will be removed in a future version.
     */
    public function getName(): string
    {
        return $this->shared->getName();
    }

    /**
     * Возвращает описание цепочки.
     *
     * @deprecated Use $chain->getSharedDefinition()->getDescription() instead. Will be removed in a future version.
     */
    public function getDescription(): string
    {
        return $this->shared->getDescription();
    }

    /**
     * Возвращает тип цепочки (static/dynamic).
     *
     * @deprecated Use $chain->getSharedDefinition()->getType() instead. Will be removed in a future version.
     */
    public function getType(): ChainTypeEnum
    {
        return $this->shared->getType();
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

    public function getFacilitator(): ?string
    {
        return $this->facilitator;
    }

    /**
     * @return list<string>
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function getMaxRounds(): int
    {
        return $this->maxRounds;
    }

    /**
     * Возвращает конфигурацию промптов для dynamic-цепочки.
     *
     * @throws LogicException если цепочка не является dynamic или промпты не заданы
     */
    public function getPromptConfiguration(): PromptConfigurationVo
    {
        return $this->promptConfiguration ?? throw new LogicException(
            sprintf('Chain "%s" does not have prompt configuration.', $this->shared->getName()),
        );
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getBrainstormSystemPrompt(): ?string
    {
        return $this->promptConfiguration?->getBrainstormSystemPrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getFacilitatorAppendPrompt(): ?string
    {
        return $this->promptConfiguration?->getFacilitatorAppendPrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getFacilitatorStartPrompt(): ?string
    {
        return $this->promptConfiguration?->getFacilitatorStartPrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getFacilitatorContinuePrompt(): ?string
    {
        return $this->promptConfiguration?->getFacilitatorContinuePrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getFacilitatorFinalizePrompt(): ?string
    {
        return $this->promptConfiguration?->getFacilitatorFinalizePrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getParticipantAppendPrompt(): ?string
    {
        return $this->promptConfiguration?->getParticipantAppendPrompt();
    }

    /**
     * @deprecated Use getPromptConfiguration() instead. Will be removed in a future version.
     */
    public function getParticipantUserPrompt(): ?string
    {
        return $this->promptConfiguration?->getParticipantUserPrompt();
    }

    /**
     * Возвращает конфигурацию роли или null, если не задана.
     *
     * @deprecated Use $chain->getSharedDefinition()->getRoleConfig($role) instead. Will be removed in a future version.
     */
    public function getRoleConfig(string $role): ?RoleConfigVo
    {
        return $this->shared->getRoleConfig($role);
    }

    /**
     * Возвращает все per-role конфигурации.
     *
     * @deprecated Use $chain->getSharedDefinition()->getRoles() instead. Will be removed in a future version.
     *
     * @return array<string, RoleConfigVo>
     */
    public function getRoles(): array
    {
        return $this->shared->getRoles();
    }

    /**
     * Является ли цепочка динамической?
     *
     * @deprecated Use $chain->getSharedDefinition()->isDynamic() instead. Will be removed in a future version.
     */
    public function isDynamic(): bool
    {
        return $this->shared->isDynamic();
    }

    /**
     * Является ли цепочка условной (conditional)?
     *
     * Условная цепочка — это цепочка с шагами, имеющими when-выражения.
     */
    public function isConditional(): bool
    {
        return $this->shared->isConditional();
    }

    /**
     * Возвращает таймаут цепочки в секундах (null = не задан, использовать CLI --timeout или default).
     *
     * @deprecated Use $chain->getSharedDefinition()->getTimeout() instead. Will be removed in a future version.
     */
    public function getTimeout(): ?int
    {
        return $this->shared->getTimeout();
    }

    /**
     * Возвращает политику retry по умолчанию для цепочки.
     */
    public function getDefaultRetryPolicy(): ?ChainRetryPolicyVo
    {
        return $this->defaultRetryPolicy;
    }

    /**
     * Возвращает бюджетные ограничения цепочки (null = безлимит).
     *
     * @deprecated Use $chain->getSharedDefinition()->getBudget() instead. Will be removed in a future version.
     */
    public function getBudget(): ?BudgetVo
    {
        return $this->shared->getBudget();
    }

    /**
     * Возвращает максимальное суммарное время выполнения цепочки в секундах (null = безлимит).
     *
     * @deprecated Use $chain->getSharedDefinition()->getMaxTime() instead. Will be removed in a future version.
     */
    public function getMaxTime(): ?int
    {
        return $this->shared->getMaxTime();
    }
}
