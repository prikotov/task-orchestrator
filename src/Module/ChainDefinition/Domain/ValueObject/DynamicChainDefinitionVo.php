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
 * Value Object определения dynamic-цепочки оркестрации.
 *
 * Содержит только dynamic-specific данные: facilitator, participants, maxRounds,
 * promptConfiguration. Общие поля — через getSharedDefinition() (SharedChainDefinitionVo).
 *
 * Immutable, readonly. Реализует ChainDefinitionInterface (ISP).
 *
 * @see ChainDefinitionInterface
 * @see SharedChainDefinitionVo
 * @see PromptConfigurationVo
 */
final readonly class DynamicChainDefinitionVo implements ChainDefinitionInterface
{
    /**
     * @param SharedChainDefinitionVo $shared общие поля (name, description, type, budget, timeout, maxTime, roles)
     * @param string $facilitator роль фасилитатора
     * @param list<string> $participants роли участников
     * @param int $maxRounds лимит раундов
     * @param PromptConfigurationVo $promptConfiguration конфигурация промптов
     * @param ChainRetryPolicyVo|null $defaultRetryPolicy политика retry по умолчанию
     *
     * @internal Используйте {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory::createFromDynamic()}.
     */
    // phpcs:ignore
    public function __construct(
        private SharedChainDefinitionVo $shared,
        private string $facilitator,
        private array $participants,
        private int $maxRounds,
        private PromptConfigurationVo $promptConfiguration,
        private ?ChainRetryPolicyVo $defaultRetryPolicy = null,
    ) {
    }

    /**
     * Создаёт dynamic-цепочку с фасилитатором и участниками.
     *
     * @param list<string> $participants
     * @param array<string, RoleConfigVo> $roles per-role конфигурация
     *
     * @deprecated Используйте {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory::createFromDynamic()}.
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

        $promptConfiguration = new PromptConfigurationVo(
            brainstormSystemPrompt: $brainstormSystemPrompt,
            facilitatorAppendPrompt: $facilitatorAppendPrompt,
            facilitatorStartPrompt: $facilitatorStartPrompt,
            facilitatorContinuePrompt: $facilitatorContinuePrompt,
            facilitatorFinalizePrompt: $facilitatorFinalizePrompt,
            participantAppendPrompt: $participantAppendPrompt,
            participantUserPrompt: $participantUserPrompt,
        );

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
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            promptConfiguration: $promptConfiguration,
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

    /**
     * Возвращает конфигурацию промптов для dynamic-цепочки.
     */
    public function getPromptConfiguration(): PromptConfigurationVo
    {
        return $this->promptConfiguration;
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
