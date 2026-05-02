<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Value Object результата проверки exec policy.
 *
 * Immutable, содержит подробную информацию о результатах проверки:
 * - разрешено или запрещено
 * - нарушенное правило (если есть)
 * - все совпавшие правила (для debug)
 */
final readonly class ExecPolicyCheckResultVo
{
    /**
     * @param bool $allowed разрешено ли выполнение
     * @param ExecRule|null $violatedRule нарушенное правило (null если разрешено или default deny)
     * @param list<ExecRule> $matchedRules все совпавшие правила (для debug)
     * @param string $checkedValue проверенное значение
     * @param RuleTargetEnum $target цель проверки
     */
    private function __construct(
        private bool $allowed,
        private ?ExecRule $violatedRule,
        private array $matchedRules,
        private string $checkedValue,
        private RuleTargetEnum $target,
    ) {
    }

    /**
     * Создаёт результат: default deny (ни одно правило не совпало).
     */
    public static function createDefaultDenied(
        string $checkedValue,
        RuleTargetEnum $target,
    ): self {
        return new self(false, null, [], $checkedValue, $target);
    }

    /**
     * Создаёт результат: разрешено.
     *
     * @param list<ExecRule> $matchedRules
     */
    public static function createAllowed(
        string $checkedValue,
        RuleTargetEnum $target,
        array $matchedRules = [],
    ): self {
        return new self(true, null, $matchedRules, $checkedValue, $target);
    }

    /**
     * Создаёт результат: запрещено нарушенным правилом.
     *
     * @param list<ExecRule> $matchedRules
     */
    public static function createDenied(
        string $checkedValue,
        RuleTargetEnum $target,
        ExecRule $violatedRule,
        array $matchedRules = [],
    ): self {
        return new self(false, $violatedRule, $matchedRules, $checkedValue, $target);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isDenied(): bool
    {
        return !$this->allowed;
    }

    public function getViolatedRule(): ?ExecRule
    {
        return $this->violatedRule;
    }

    /**
     * @return list<ExecRule>
     */
    public function getMatchedRules(): array
    {
        return $this->matchedRules;
    }

    public function getCheckedValue(): string
    {
        return $this->checkedValue;
    }

    public function getTarget(): RuleTargetEnum
    {
        return $this->target;
    }
}
