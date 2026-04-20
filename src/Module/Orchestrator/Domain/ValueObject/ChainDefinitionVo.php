<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FixIterationGroupVo as FixIterationGroupVo;
use InvalidArgumentException;

/**
 * Value Object определения цепочки оркестрации.
 *
 * Immutable, содержит параметры цепочки.
 * Поддерживает два типа: static (линейные шаги) и dynamic (фасилитатор + участники).
 */
final readonly class ChainDefinitionVo
{
    /**
     * @param string $name имя цепочки
     * @param string $description описание
     * @param ChainTypeEnum $type тип цепочки (static/dynamic)
     * @param list<ChainStepVo> $steps шаги для static-цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы итераций фикса
     * @param string|null $facilitator роль фасилитатора (для dynamic)
     * @param list<string> $participants роли участников (для dynamic)
     * @param int $maxRounds лимит раундов (для dynamic)
     * @param string|null $brainstormSystemPrompt базовый системный промпт (упрощённый Pi default) для --system-prompt
     * @param string|null $facilitatorAppendPrompt промпт фасилитатора для --append-system-prompt (%s → participants)
     * @param string|null $facilitatorStartPrompt промпт первого вызова фасилитатора (%s → topic)
     * @param string|null $facilitatorContinuePrompt промпт продолжения фасилитатора (%s → topic, %s → journal, %s → history)
     * @param string|null $facilitatorFinalizePrompt промпт финализации (%s → topic, %s → history)
     * @param string|null $participantAppendPrompt промпт участника для --append-system-prompt (%s → role_file)
     * @param string|null $participantUserPrompt пользовательский промпт участника (%s → topic, %s → history)
     * @param array<string, RoleConfigVo> $roles per-role конфигурация (key = role name)
     * @param ChainRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию для шагов цепочки
     * @param BudgetVo|null $budget бюджетные ограничения цепочки (null = безлимит)
     * @param int|null $timeout таймаут цепочки в секундах (null = использовать CLI --timeout или default)
     */
    private function __construct(
        private string $name,
        private string $description,
        private ChainTypeEnum $type,
        private array $steps,
        private array $fixIterations,
        private ?string $facilitator,
        private array $participants,
        private int $maxRounds,
        private ?string $brainstormSystemPrompt,
        private ?string $facilitatorAppendPrompt,
        private ?string $facilitatorStartPrompt,
        private ?string $facilitatorContinuePrompt,
        private ?string $facilitatorFinalizePrompt,
        private ?string $participantAppendPrompt,
        private ?string $participantUserPrompt,
        private array $roles = [],
        private ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        private ?BudgetVo $budget = null,
        private ?int $timeout = null,
    ) {
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
        if (count($steps) === 0) {
            throw new InvalidArgumentException(
                sprintf('Chain "%s" must have at least one step.', $name),
            );
        }

        self::validateFixIterations($name, $steps, $fixIterations);

        return new self(
            name: $name,
            description: $description,
            type: ChainTypeEnum::staticType,
            steps: $steps,
            fixIterations: $fixIterations,
            facilitator: null,
            participants: [],
            maxRounds: 10,
            brainstormSystemPrompt: null,
            facilitatorAppendPrompt: null,
            facilitatorStartPrompt: null,
            facilitatorContinuePrompt: null,
            facilitatorFinalizePrompt: null,
            participantAppendPrompt: null,
            participantUserPrompt: null,
            roles: $roles,
            defaultRetryPolicy: $defaultRetryPolicy,
            budget: $budget,
            timeout: $timeout,
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
            name: $name,
            description: $description,
            type: ChainTypeEnum::dynamicType,
            steps: [],
            fixIterations: [],
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            brainstormSystemPrompt: $brainstormSystemPrompt,
            facilitatorAppendPrompt: $facilitatorAppendPrompt,
            facilitatorStartPrompt: $facilitatorStartPrompt,
            facilitatorContinuePrompt: $facilitatorContinuePrompt,
            facilitatorFinalizePrompt: $facilitatorFinalizePrompt,
            participantAppendPrompt: $participantAppendPrompt,
            participantUserPrompt: $participantUserPrompt,
            roles: $roles,
            defaultRetryPolicy: $defaultRetryPolicy,
            budget: $budget,
            timeout: $timeout,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): ChainTypeEnum
    {
        return $this->type;
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

    public function getBrainstormSystemPrompt(): ?string
    {
        return $this->brainstormSystemPrompt;
    }

    public function getFacilitatorAppendPrompt(): ?string
    {
        return $this->facilitatorAppendPrompt;
    }

    public function getFacilitatorStartPrompt(): ?string
    {
        return $this->facilitatorStartPrompt;
    }

    public function getFacilitatorContinuePrompt(): ?string
    {
        return $this->facilitatorContinuePrompt;
    }

    public function getFacilitatorFinalizePrompt(): ?string
    {
        return $this->facilitatorFinalizePrompt;
    }

    public function getParticipantAppendPrompt(): ?string
    {
        return $this->participantAppendPrompt;
    }

    public function getParticipantUserPrompt(): ?string
    {
        return $this->participantUserPrompt;
    }

    /**
     * Возвращает конфигурацию роли или null, если не задана.
     */
    public function getRoleConfig(string $role): ?RoleConfigVo
    {
        return $this->roles[$role] ?? null;
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
     * Является ли цепочка динамической?
     */
    public function isDynamic(): bool
    {
        return $this->type === ChainTypeEnum::dynamicType;
    }

    /**
     * Возвращает таймаут цепочки в секундах (null = не задан, использовать CLI --timeout или default).
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
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
     */
    public function getBudget(): ?BudgetVo
    {
        return $this->budget;
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

        // Собираем map name → index
        $nameMap = [];
        foreach ($steps as $index => $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = $index;
            }
        }

        // Проверяем что все stepNames из групп существуют
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
