<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\InvalidFixIterationsException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\PromptConfigurationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;

/**
 * Фабрика определений цепочек оркестрации.
 *
 * Авторитетная граница создания специализированных VO (StaticChainDefinitionVo,
 * ConditionalChainDefinitionVo, DynamicChainDefinitionVo): централизует guard-проверки
 * инвариантов и через DI получает доменную спецификацию fix-итераций.
 *
 * Фабрика кидает fail-fast domain-исключение {@see InvalidFixIterationsException} при
 * нарушении инварианта ссылочной целостности fix-итераций. Сообщение остаётся generic
 * (без имени группы/шага), но исключение несёт raw-входные данные, чтобы validate-путь
 * мог получить детальную диагностику (имя группы, имя шага) через единый источник —
 * коллектор {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsServiceInterface}.
 * Фабрика намеренно не дублирует форматирование detailed-сообщений, чтобы не плодить
 * второй источник. Run-путь остаётся fail-fast и визуально неизменным.
 *
 * Не выполняет I/O, не зависит от внешних слоёв.
 *
 * @see docs/conventions/core_patterns/factory.md
 */
final readonly class ChainDefinitionFactory
{
    public function __construct(
        private FixIterationsReferenceIntegritySpecification $fixIterationsReferenceIntegritySpecification,
    ) {
    }

    /**
     * Создаёт static-цепочку с линейными шагами.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     *
     * @throws InvalidFixIterationsException если нарушен инвариант ссылочной целостности fix-итераций
     * @throws InvalidArgumentException если шагов нет (chain без шагов)
     */
    public function createFromSteps(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): StaticChainDefinitionVo {
        if (count($steps) === 0) {
            throw new InvalidArgumentException(
                sprintf('Chain "%s" must have at least one step.', $name),
            );
        }

        $this->assertStepBasedInvariant($name, $steps, $fixIterations);

        return new StaticChainDefinitionVo(
            shared: $this->createSharedDefinition($name, $description, ChainTypeEnum::staticType, $budget, $timeout, null, $roles),
            steps: $steps,
            fixIterations: $fixIterations,
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    /**
     * Создаёт conditional-цепочку — статическую цепочку с условным ветвлением шагов.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     *
     * @throws InvalidFixIterationsException если нарушен инвариант ссылочной целостности fix-итераций
     * @throws InvalidArgumentException если шагов нет (chain без шагов)
     */
    public function createFromConditionalSteps(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): ConditionalChainDefinitionVo {
        if (count($steps) === 0) {
            throw new InvalidArgumentException(
                sprintf('Chain "%s" must have at least one step.', $name),
            );
        }

        $this->assertStepBasedInvariant($name, $steps, $fixIterations);

        return new ConditionalChainDefinitionVo(
            shared: $this->createSharedDefinition($name, $description, ChainTypeEnum::conditionalType, $budget, $timeout, null, $roles),
            steps: $steps,
            fixIterations: $fixIterations,
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    /**
     * Создаёт dynamic-цепочку с фасилитатором и участниками.
     *
     * @param list<string> $participants
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     *
     * @throws InvalidArgumentException если не задан фасилитатор, нет участников или пустые промпты
     */
    public function createFromDynamic(
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
    ): DynamicChainDefinitionVo {
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

        $promptConfiguration = new PromptConfigurationVo(
            brainstormSystemPrompt: $brainstormSystemPrompt,
            facilitatorAppendPrompt: $facilitatorAppendPrompt,
            facilitatorStartPrompt: $facilitatorStartPrompt,
            facilitatorContinuePrompt: $facilitatorContinuePrompt,
            facilitatorFinalizePrompt: $facilitatorFinalizePrompt,
            participantAppendPrompt: $participantAppendPrompt,
            participantUserPrompt: $participantUserPrompt,
        );

        return new DynamicChainDefinitionVo(
            shared: $this->createSharedDefinition($name, $description, ChainTypeEnum::dynamicType, $budget, $timeout, $maxTime, $roles),
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            promptConfiguration: $promptConfiguration,
            defaultRetryPolicy: $defaultRetryPolicy,
        );
    }

    /**
     * Проверяет инвариант ссылочной целостности fix-итераций и кидает carrier-исключение
     * {@see InvalidFixIterationsException} (сообщение generic; исключение несёт raw-входы
     * для detailed-диагностики в validate-пути).
     *
     * Сообщение намеренно общее: не собирает имя группы/шага, чтобы не дублировать
     * детальную диагностику ChainDefinitionValidatorService.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     *
     * @throws InvalidFixIterationsException если спецификация не выполнена
     */
    private function assertStepBasedInvariant(string $name, array $steps, array $fixIterations): void
    {
        if ($this->fixIterationsReferenceIntegritySpecification->isSatisfiedBy($steps, $fixIterations)) {
            return;
        }

        // Carrier-исключение несёт raw-входные данные для detailed-диагностики
        // в validate-пути; getMessage() сохраняет прежний generic-текст (run-путь
        // визуально неизменен). Зависимости фабрики не меняются (только Specification).
        throw new InvalidFixIterationsException($name, $steps, $fixIterations);
    }

    /**
     * @param array<string, RoleConfigVo> $roles
     */
    private function createSharedDefinition(
        string $name,
        string $description,
        ChainTypeEnum $type,
        ?BudgetVo $budget,
        ?int $timeout,
        ?int $maxTime,
        array $roles,
    ): SharedChainDefinitionVo {
        return new SharedChainDefinitionVo(
            name: $name,
            description: $description,
            type: $type,
            budget: $budget,
            timeout: $timeout,
            maxTime: $maxTime,
            roles: $roles,
        );
    }
}
