<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Value Object разрешения/запрета на конкретный ресурс.
 *
 * Immutable, представляет элементарное право доступа:
 * target + resource + action (allow/deny).
 *
 * Используется в PermissionSet для chain-level authorization.
 */
final readonly class PermissionVo
{
    private function __construct(
        private RuleTargetEnum $target,
        private string $resource,
        private RuleActionEnum $action,
    ) {
        if ($resource === '') {
            throw new \InvalidArgumentException('Permission resource must not be empty.');
        }
    }

    /**
     * Создаёт разрешение (allow).
     */
    public static function allow(RuleTargetEnum $target, string $resource): self
    {
        return new self($target, $resource, RuleActionEnum::allow);
    }

    /**
     * Создаёт запрет (deny).
     */
    public static function deny(RuleTargetEnum $target, string $resource): self
    {
        return new self($target, $resource, RuleActionEnum::deny);
    }

    /**
     * Проверяет, совпадает ли permission с указанным target и resource.
     */
    public function matches(RuleTargetEnum $target, string $resource): bool
    {
        return $this->target === $target && $this->resource === $resource;
    }

    public function getTarget(): RuleTargetEnum
    {
        return $this->target;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getAction(): RuleActionEnum
    {
        return $this->action;
    }

    public function isDeny(): bool
    {
        return $this->action === RuleActionEnum::deny;
    }

    public function isAllow(): bool
    {
        return $this->action === RuleActionEnum::allow;
    }

    public function equals(self $other): bool
    {
        return $this->target === $other->target
            && $this->resource === $other->resource
            && $this->action === $other->action;
    }
}
