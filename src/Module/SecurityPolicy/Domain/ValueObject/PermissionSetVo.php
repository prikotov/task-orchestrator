<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Value Object набора разрешений для цепочки.
 *
 * Immutable, содержит список PermissionVo и реализует deny-first логику:
 * 1. Если хотя бы один deny совпадает с target+resource → denied
 * 2. Если только allow совпадает → allowed
 * 3. Если ни одно правило не совпало → defaultDeny (по умолчанию true)
 */
final readonly class PermissionSetVo
{
    /**
     * @param list<PermissionVo> $permissions набор разрешений
     * @param bool $defaultDeny политика по умолчанию при отсутствии совпадений (true = deny)
     */
    private function __construct(
        private array $permissions,
        private bool $defaultDeny,
    ) {
    }

    /**
     * Создаёт пустой набор с deny-by-default политикой.
     */
    public static function createDefaultDeny(): self
    {
        return new self([], true);
    }

    /**
     * Создаёт пустой набор с allow-by-default политикой.
     */
    public static function createDefaultAllow(): self
    {
        return new self([], false);
    }

    /**
     * Создаёт набор из списка permissions с deny-by-default.
     *
     * @param list<PermissionVo> $permissions
     */
    public static function createFromPermissions(array $permissions, bool $defaultDeny = true): self
    {
        return new self($permissions, $defaultDeny);
    }

    /**
     * Проверяет, разрешён ли доступ к target+resource.
     *
     * Deny-first логика:
     * 1. Если хотя бы один deny совпадает → false
     * 2. Если только allow совпадает → true
     * 3. Если ни одно правило не совпало → defaultDeny
     */
    public function isAllowed(RuleTargetEnum $target, string $resource): bool
    {
        $hasAllow = false;

        foreach ($this->permissions as $permission) {
            if (!$permission->matches($target, $resource)) {
                continue;
            }

            if ($permission->isDeny()) {
                return false;
            }

            $hasAllow = true;
        }

        if ($hasAllow) {
            return true;
        }

        return !$this->defaultDeny;
    }

    /**
     * Возвращает все deny-permissions для указанного target.
     *
     * @return list<PermissionVo>
     */
    public function getDenyPermissions(RuleTargetEnum $target): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                static fn (PermissionVo $p): bool => $p->getTarget() === $target && $p->isDeny(),
            ),
        );
    }

    /**
     * Возвращает все allow-permissions для указанного target.
     *
     * @return list<PermissionVo>
     */
    public function getAllowPermissions(RuleTargetEnum $target): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                static fn (PermissionVo $p): bool => $p->getTarget() === $target && $p->isAllow(),
            ),
        );
    }

    /**
     * @return list<PermissionVo>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function isDefaultDeny(): bool
    {
        return $this->defaultDeny;
    }
}
