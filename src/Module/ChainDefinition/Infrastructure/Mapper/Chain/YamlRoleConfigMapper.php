<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;

/**
 * Маппер секции `roles` из raw YAML в {@see RoleConfigVo}.
 *
 * Вынесено из
 * {@see \TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService}:
 * загрузчик цепочек отвечает за разбор цепочек, техническое преобразование
 * конфигурации ролей — здесь, по образцу соседних {@see YamlChainStepMapper} /
 * {@see YamlRetryPolicyMapper}.
 *
 * Каждый элемент roles: { command, command_profile, commands_by_complexity,
 * timeout, prompt_file, system_prompt_file, fallback }. Все поля опциональны.
 *
 * Поле `commands_by_complexity` валидируется fail-fast (см.
 * self::resolveCommandsByComplexity()) и попадает в RoleConfigVo: имена
 * профилей резолвятся в полные команды при загрузке, выбор команды по
 * сложности выполняет RoleConfigVo::resolveCommandByComplexity() на границе
 * модулей (Integration-мапперы ChainExecution/DynamicLoop).
 *
 * Формат fallback в YAML:
 *   fallback:
 *     command:
 *       - codex
 *       - --model
 *       - gpt-4o
 *
 * Stateless, без I/O: только техническое преобразование внешнего формата
 * (raw YAML) в доменные VO.
 */
final readonly class YamlRoleConfigMapper
{
    /**
     * Маппит raw-секцию `roles` в карту доменных VO ролей.
     *
     * @param array<string, mixed> $raw секция `roles` из YAML
     * @param array<string, array{command: list<string>}> $profiles секция `command_profiles`
     *
     * @return array<string, RoleConfigVo>
     *
     * @throws InvalidArgumentException при неизвестном командном профиле или некорректном commands_by_complexity
     */
    public function mapToRoles(array $raw, array $profiles): array
    {
        $roles = [];

        foreach ($raw as $roleName => $config) {
            if (!is_array($config)) {
                continue;
            }

            $command = $config['command'] ?? [];
            if (isset($config['command_profile'])) {
                $profileName = $config['command_profile'];
                if (!isset($profiles[$profileName]['command'])) {
                    throw new InvalidArgumentException(sprintf('Unknown command profile "%s" for role "%s".', $profileName, $roleName));
                }
                $command = $profiles[$profileName]['command'];
            }

            $roles[$roleName] = new RoleConfigVo(
                command: $command,
                timeout: $config['timeout'] ?? null,
                promptFile: $config['prompt_file'] ?? null,
                commandsByComplexity: $this->resolveCommandsByComplexity($roleName, $profiles, $config),
                systemPromptFile: $config['system_prompt_file'] ?? null,
                fallback: $this->parseFallbackConfig($config),
            );
        }

        return $roles;
    }

    /**
     * Валидирует fail-fast и резолвит поле roles.<name>.commands_by_complexity.
     *
     * Значения поля — имена существующих профилей из command_profiles либо непустые
     * inline-команды; в VO попадают только полные команды (list<string>). Некорректное
     * поле ломает загрузку конфигурации немедленно, а не при первом запуске агента.
     *
     * Правила:
     * - ключ — уровень строго из диапазона C0–C5;
     * - строковое значение — имя существующего профиля из command_profiles;
     * - массив-значение — непустая inline CLI-команда.
     *
     * @param array<string, mixed> $roleConfig
     * @param array<string, array{command: list<string>}> $profiles
     *
     * @return array<string, list<string>> уровень C0–C5 → полная CLI-команда уровня
     *
     * @throws InvalidArgumentException при неверном уровне, неизвестном профиле или пустой inline-команде
     */
    private function resolveCommandsByComplexity(string $roleName, array $profiles, array $roleConfig): array
    {
        $commandsByComplexity = $roleConfig['commands_by_complexity'] ?? [];

        if ($commandsByComplexity === []) {
            return [];
        }

        if (!is_array($commandsByComplexity)) {
            throw new InvalidArgumentException(sprintf(
                'Role "%s": commands_by_complexity must be a map of complexity levels (C0-C5) to command profiles or inline commands.',
                $roleName,
            ));
        }

        $resolved = [];

        foreach ($commandsByComplexity as $level => $value) {
            if (!is_string($level) || preg_match('/^C[0-5]$/', $level) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Role "%s": invalid task complexity level "%s" in commands_by_complexity (expected C0-C5).',
                    $roleName,
                    is_string($level) ? $level : get_debug_type($level),
                ));
            }

            if (is_array($value)) {
                if ($value === []) {
                    throw new InvalidArgumentException(sprintf(
                        'Role "%s": inline command for task complexity "%s" must not be empty.',
                        $roleName,
                        $level,
                    ));
                }
                /** @var list<string> $value array_map сохраняет непрерывную нумерацию */
                $resolved[$level] = array_map('strval', $value);
                continue;
            }

            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Role "%s": commands_by_complexity.%s must be a command profile name or inline command, %s given.',
                    $roleName,
                    $level,
                    get_debug_type($value),
                ));
            }

            if (!isset($profiles[$value]['command'])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown command profile "%s" for role "%s" at complexity level "%s".',
                    $value,
                    $roleName,
                    $level,
                ));
            }

            $resolved[$level] = $profiles[$value]['command'];
        }

        return $resolved;
    }

    /**
     * Парсит fallback-конфигурацию из секции роли.
     *
     * @param array<string, mixed> $roleConfig
     */
    private function parseFallbackConfig(array $roleConfig): ?FallbackConfigVo
    {
        $fallback = $roleConfig['fallback'] ?? null;

        if (!is_array($fallback)) {
            return null;
        }

        $command = $fallback['command'] ?? [];

        if (!is_array($command) || $command === []) {
            return null;
        }

        /** @var list<string> $commandList */
        $commandList = array_values(array_map('strval', $command));

        return new FallbackConfigVo(command: $commandList);
    }
}
