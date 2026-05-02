<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

/**
 * In-memory сущность декларативного правила безопасности.
 *
 * Описывает одно правило exec policy:
 * - id — уникальный идентификатор правила
 * - target — к чему применяется (command, runner, tool, model)
 * - pattern — шаблон матчинга (glob/regex/exact)
 * - action — разрешить или запретить
 * - severity — строгость (block — блокировка, warn — предупреждение)
 * - priority — приоритет при конфликтах (выше = важнее)
 * - description — человекочитаемое описание
 *
 * Не является aggregate root. Persistence — в Infrastructure (Task 4).
 */
final class ExecRule
{
    private readonly ExecRuleIdVo $id;
    private readonly RuleTargetEnum $target;
    private readonly RulePatternVo $pattern;
    private readonly RuleActionEnum $action;
    private readonly RuleSeverityEnum $severity;
    private readonly int $priority;
    private readonly string $description;

    public function __construct(
        ExecRuleIdVo $id,
        RuleTargetEnum $target,
        RulePatternVo $pattern,
        RuleActionEnum $action,
        RuleSeverityEnum $severity = RuleSeverityEnum::block,
        int $priority = 0,
        string $description = '',
    ) {
        $this->id = $id;
        $this->target = $target;
        $this->pattern = $pattern;
        $this->action = $action;
        $this->severity = $severity;
        $this->priority = $priority;
        $this->description = $description;
    }

    /**
     * Проверяет, совпадает ли значение с паттерном правила.
     */
    public function matches(string $value): bool
    {
        return $this->pattern->matches($value);
    }

    /**
     * Проверяет, совпадает ли цель правила с указанной.
     */
    public function targets(RuleTargetEnum $target): bool
    {
        return $this->target === $target;
    }

    public function isDeny(): bool
    {
        return $this->action === RuleActionEnum::deny;
    }

    public function isAllow(): bool
    {
        return $this->action === RuleActionEnum::allow;
    }

    public function isBlock(): bool
    {
        return $this->severity === RuleSeverityEnum::block;
    }

    public function isWarn(): bool
    {
        return $this->severity === RuleSeverityEnum::warn;
    }

    public function getId(): ExecRuleIdVo
    {
        return $this->id;
    }

    public function getTarget(): RuleTargetEnum
    {
        return $this->target;
    }

    public function getPattern(): RulePatternVo
    {
        return $this->pattern;
    }

    public function getAction(): RuleActionEnum
    {
        return $this->action;
    }

    public function getSeverity(): RuleSeverityEnum
    {
        return $this->severity;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
