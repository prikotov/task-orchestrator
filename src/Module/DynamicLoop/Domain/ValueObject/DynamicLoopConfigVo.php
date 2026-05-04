<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object конфигурации dynamic-цикла.
 *
 * Самодостаточный VO — содержит все данные для запуска dynamic-цикла,
 * без зависимости от ChainDefinition.Domain.
 *
 * Содержит:
 * - shared-поля: name, budget, timeout, maxTime, roles, roleConfigs
 * - dynamic-поля: facilitator, participants, maxRounds, promptConfiguration
 * - retry: defaultRetryPolicy
 *
 * Immutable, readonly.
 */
final readonly class DynamicLoopConfigVo
{
    /**
     * @param string $name имя цепочки
     * @param string $description описание
     * @param DynamicLoopBudgetVo|null $budget бюджетные ограничения
     * @param int|null $timeout таймаут цепочки в секундах
     * @param int|null $maxTime максимальное суммарное время выполнения
     * @param array<string, DynamicLoopRoleConfigVo> $roleConfigs per-role конфигурация
     * @param string $facilitator роль фасилитатора
     * @param list<string> $participants роли участников
     * @param int $maxRounds лимит раундов
     * @param DynamicLoopPromptConfigVo $promptConfiguration конфигурация промптов
     * @param DynamicLoopRetryPolicyVo|null $defaultRetryPolicy политика retry
     */
    public function __construct(
        private string $name,
        private string $description,
        private ?DynamicLoopBudgetVo $budget,
        private ?int $timeout,
        private ?int $maxTime,
        private array $roleConfigs,
        private string $facilitator,
        private array $participants,
        private int $maxRounds,
        private DynamicLoopPromptConfigVo $promptConfiguration,
        private ?DynamicLoopRetryPolicyVo $defaultRetryPolicy = null,
    ) {
    }

    /**
     * Создаёт конфигурацию dynamic-цикла.
     *
     * @param list<string> $participants
     * @param array<string, DynamicLoopRoleConfigVo> $roleConfigs
     */
    public static function create(
        string $name,
        string $description,
        string $facilitator,
        array $participants,
        int $maxRounds,
        DynamicLoopPromptConfigVo $promptConfiguration,
        array $roleConfigs = [],
        ?DynamicLoopRetryPolicyVo $defaultRetryPolicy = null,
        ?DynamicLoopBudgetVo $budget = null,
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

        return new self(
            name: $name,
            description: $description,
            budget: $budget,
            timeout: $timeout,
            maxTime: $maxTime,
            roleConfigs: $roleConfigs,
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            promptConfiguration: $promptConfiguration,
            defaultRetryPolicy: $defaultRetryPolicy,
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

    public function getBudget(): ?DynamicLoopBudgetVo
    {
        return $this->budget;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getMaxTime(): ?int
    {
        return $this->maxTime;
    }

    /**
     * @return array<string, DynamicLoopRoleConfigVo>
     */
    public function getRoleConfigs(): array
    {
        return $this->roleConfigs;
    }

    public function getRoleConfig(string $role): ?DynamicLoopRoleConfigVo
    {
        return $this->roleConfigs[$role] ?? null;
    }

    public function getFacilitator(): string
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

    public function getPromptConfiguration(): DynamicLoopPromptConfigVo
    {
        return $this->promptConfiguration;
    }

    public function getDefaultRetryPolicy(): ?DynamicLoopRetryPolicyVo
    {
        return $this->defaultRetryPolicy;
    }
}
