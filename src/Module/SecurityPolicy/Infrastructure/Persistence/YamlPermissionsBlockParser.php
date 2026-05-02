<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Persistence;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionVo;

/**
 * Parser: преобразует permissions block из chain YAML в PermissionSetVo.
 *
 * Поддерживаемый формат permissions block:
 *
 * ```yaml
 * permissions:
 *   runners:
 *     allow: [openai, anthropic]
 *     deny: [local-shell]
 *   tools:
 *     allow: [file_read, file_write]
 *   commands:
 *     deny:
 *       - pattern: "rm -rf *"
 *         type: glob
 *   models:
 *     allow: [gpt-4, claude-3.5]
 *   severity: block
 * ```
 *
 * Каждая секция (runners, tools, commands, models) опциональна.
 * Если `permissions:` отсутствует — возвращает default PermissionSetVo (allow-by-default).
 *
 * @see PermissionSetVo
 * @see PermissionVo
 */
final class YamlPermissionsBlockParser
{
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface|null $logger опциональный логгер для warning при некорректных данных
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Парсит permissions block из chain config в PermissionSetVo.
     *
     * @param array<string, mixed>|null $permissionsBlock секция permissions из YAML (или null если отсутствует)
     *
     * @return PermissionSetVo набор разрешений (allow-by-default если block пуст)
     */
    public function parse(?array $permissionsBlock): PermissionSetVo
    {
        if ($permissionsBlock === null || $permissionsBlock === []) {
            return PermissionSetVo::createDefaultAllow();
        }

        $permissions = [];

        // Runners: allow/deny списки
        $permissions = array_merge(
            $permissions,
            $this->parseTargetSection(
                $permissionsBlock['runners'] ?? null,
                RuleTargetEnum::runner,
            ),
        );

        // Tools: allow/deny списки
        $permissions = array_merge(
            $permissions,
            $this->parseTargetSection(
                $permissionsBlock['tools'] ?? null,
                RuleTargetEnum::tool,
            ),
        );

        // Models: allow/deny списки
        $permissions = array_merge(
            $permissions,
            $this->parseTargetSection(
                $permissionsBlock['models'] ?? null,
                RuleTargetEnum::model,
            ),
        );

        // Commands: deny-списки с pattern/type
        $permissions = array_merge(
            $permissions,
            $this->parseCommandsSection($permissionsBlock['commands'] ?? null),
        );

        // Если не указано ни одного разрешения — allow-by-default
        if ($permissions === []) {
            return PermissionSetVo::createDefaultAllow();
        }

        // Определяем default deny policy: если есть хотя бы один allow для target,
        // всё что не указано в allow — deny (white-list mode).
        // Если только deny — default allow (black-list mode).
        $defaultDeny = $this->shouldDefaultDeny($permissions);

        return PermissionSetVo::createFromPermissions($permissions, $defaultDeny);
    }

    /**
     * Парсит секцию target (runners, tools, models).
     *
     * Формат:
     *   runners:
     *     allow: [openai, anthropic]
     *     deny: [local-shell]
     *
     * @param array<string, mixed>|null $section
     *
     * @return list<PermissionVo>
     */
    private function parseTargetSection(?array $section, RuleTargetEnum $target): array
    {
        if ($section === null || $section === []) {
            return [];
        }

        $permissions = [];

        // Allow list
        $allowList = $section['allow'] ?? [];
        if (\is_array($allowList)) {
            foreach ($allowList as $resource) {
                $resourceStr = (string) $resource;
                if ($resourceStr === '') {
                    continue;
                }
                $permissions[] = PermissionVo::allow($target, $resourceStr);
            }
        }

        // Deny list
        $denyList = $section['deny'] ?? [];
        if (\is_array($denyList)) {
            foreach ($denyList as $resource) {
                $resourceStr = (string) $resource;
                if ($resourceStr === '') {
                    continue;
                }
                $permissions[] = PermissionVo::deny($target, $resourceStr);
            }
        }

        return $permissions;
    }

    /**
     * Парсит секцию commands с поддержкой pattern/type.
     *
     * Формат:
     *   commands:
     *     allow: [ls, cat]       # exact match
     *     deny:
     *       - pattern: "rm -rf *"
     *         type: glob
     *
     * @param array<string, mixed>|null $section
     *
     * @return list<PermissionVo>
     */
    private function parseCommandsSection(?array $section): array
    {
        if ($section === null || $section === []) {
            return [];
        }

        $permissions = [];

        // Allow list (exact match strings)
        $allowList = $section['allow'] ?? [];
        if (\is_array($allowList)) {
            foreach ($allowList as $resource) {
                $resourceStr = (string) $resource;
                if ($resourceStr === '') {
                    continue;
                }
                $permissions[] = PermissionVo::allow(RuleTargetEnum::command, $resourceStr);
            }
        }

        // Deny list (строки или объекты с pattern/type)
        $denyList = $section['deny'] ?? [];
        if (\is_array($denyList)) {
            foreach ($denyList as $item) {
                if (\is_string($item)) {
                    // Простой строковый deny
                    $permissions[] = PermissionVo::deny(RuleTargetEnum::command, $item);
                } elseif (\is_array($item) && isset($item['pattern'])) {
                    // Pattern-based deny — ресурс = pattern строка
                    $pattern = (string) $item['pattern'];
                    if ($pattern !== '') {
                        $permissions[] = PermissionVo::deny(RuleTargetEnum::command, $pattern);
                    }
                }
            }
        }

        return $permissions;
    }

    /**
     * Определяет, нужно ли использовать deny-by-default.
     *
     * Если для любого target есть allow-правила, то всё неуказанное — deny.
     * Если только deny-правила — default allow (black-list mode).
     *
     * @param list<PermissionVo> $permissions
     */
    private function shouldDefaultDeny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($permission->isAllow()) {
                return true;
            }
        }

        return false;
    }
}
