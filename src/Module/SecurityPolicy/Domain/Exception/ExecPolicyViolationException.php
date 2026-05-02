<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Нарушение exec-level security policy.
 *
 * Выбрасывается, когда команда, runner, tool или модель
 * не проходит проверку exec rules.
 * Содержит информацию о нарушенном правиле.
 */
final class ExecPolicyViolationException extends SecurityPolicyException
{
    private ?string $ruleId = null;
    private ?string $pattern = null;
    private ?RuleTargetEnum $target = null;
    private ?string $violatedValue = null;

    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Создаёт исключение с информацией о нарушенном правиле.
     */
    public static function createFromRule(
        string $ruleId,
        RuleTargetEnum $target,
        string $pattern,
        string $violatedValue,
        ?\Throwable $previous = null,
    ): self {
        $exception = new self(
            sprintf(
                'Exec policy violation: rule "%s" denies %s "%s" (pattern: "%s")',
                $ruleId,
                $target->value,
                $violatedValue,
                $pattern,
            ),
            $previous,
        );
        $exception->ruleId = $ruleId;
        $exception->pattern = $pattern;
        $exception->target = $target;
        $exception->violatedValue = $violatedValue;

        return $exception;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getTarget(): ?RuleTargetEnum
    {
        return $this->target;
    }

    public function getViolatedValue(): ?string
    {
        return $this->violatedValue;
    }
}
