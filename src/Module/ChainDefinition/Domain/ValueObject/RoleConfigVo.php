<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object конфигурации роли для оркестрации.
 *
 * Описывает per-role настройки: полная CLI-команда, таймаут, пути к базовому и ролевому промптам,
 * а также опциональный fallback — альтернативную CLI-команду для запуска при ошибке основного runner'а.
 * Определяется в YAML (секция roles) и применяется handler при запуске агента.
 */
final readonly class RoleConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда для запуска агента.
     *        Например: [pi, --mode, json, -p, --no-session, --model, gpt-4o-mini, --system-prompt, @system-prompt]
     *        Маркеры вида @system-prompt / @append-system-prompt резолвятся в абсолютные пути к файлам сессии;
     *        Pi прочитает файл самостоятельно через existsSync-эвристику.
     *        Остальные значения с префиксом @ интерпретируются как пути к файлам
     *        (runner подставляет содержимое файла вместо @path).
     *        Если пусто — runner использует команду по умолчанию.
     * @param int|null $timeout таймаут в секундах. Если null — из chain или default.
     * @param string|null $promptFile путь к файлу описания роли (относительно корня проекта).
     *        Если null — используется неявная конвенция {rolesDir}/{roleName}.ru.md.
     * @param array<string, list<string>> $commandsByComplexity отклонения команды по уровням сложности C0–C5.
     *        Ключ — уровень строго из диапазона C0–C5; значение — полная CLI-команда уровня
     *        (имена профилей резолвятся при загрузке YAML). Базовая $command неявно считается уровнем C3.
     * @param string|null $systemPromptFile путь к базовому системному промпту. Если задан,
     *        promptFile передаётся через append-system-prompt, а не заменяет базовый промпт.
     * @param FallbackConfigVo|null $fallback конфигурация fallback runner'а.
     *        Определяет альтернативную CLI-команду для запуска при ошибке основного runner'а.
     *        Первый элемент fallback.command — имя runner'а в registry.
     *        Если null — fallback не сконфигурирован, при ошибке шаг завершается с ошибкой.
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private array $commandsByComplexity = [],
        private ?string $systemPromptFile = null,
        private ?FallbackConfigVo $fallback = null,
    ) {
        foreach ($commandsByComplexity as $level => $levelCommand) {
            if (preg_match('/^C[0-5]$/', $level) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid task complexity level "%s".', $level));
            }

            if ($levelCommand === []) {
                throw new InvalidArgumentException(sprintf('Command for task complexity "%s" must not be empty.', $level));
            }
        }
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    /**
     * Возвращает отклонения команды по уровням сложности задачи.
     *
     * @return array<string, list<string>> уровень C0–C5 → полная CLI-команда уровня.
     */
    public function getCommandsByComplexity(): array
    {
        return $this->commandsByComplexity;
    }

    /**
     * Выбирает целую CLI-команду роли по сложности задачи.
     *
     * Сложность передаётся целым числом 0–5 (уровень «C» + число, например C3 → 3):
     * скалярный контракт пересекает границы модулей без связи с чужими типами
     * и совпадает с внутренним контрактом watch-subagent.sh (навык run-subagent).
     *
     * Правило выбора (единое с навыком run-subagent):
     * - базовая команда неявно регистрируется как уровень C3 (если C3 не задан явно);
     * - точный уровень, иначе ближайший настроенный уровень выше,
     *   иначе ближайший настроенный уровень ниже;
     * - если отклонений нет вовсе — базовая команда.
     * Единственный отклик C0/C5 работает по тому же правилу:
     * уровни ниже/выше него уходят к базовой команде через C3.
     *
     * @param int $complexity уровень сложности задачи: 0–5 (C0–C5).
     *
     * @throws InvalidArgumentException если уровень вне диапазона 0–5.
     *
     * @return list<string> полная CLI-команда для запуска агента.
     */
    public function resolveCommandByComplexity(int $complexity): array
    {
        if ($complexity < 0 || $complexity > 5) {
            throw new InvalidArgumentException(sprintf(
                'Invalid task complexity %d: expected 0-5 (C0-C5).',
                $complexity,
            ));
        }

        $commands = $this->commandsByComplexity;

        if ($this->command !== [] && !isset($commands['C3'])) {
            $commands['C3'] = $this->command;
        }

        $requestedLevel = $complexity;

        foreach (range($requestedLevel, 5) as $level) {
            $levelCommand = $commands['C' . $level] ?? null;

            if ($levelCommand !== null) {
                return $levelCommand;
            }
        }

        for ($level = $requestedLevel - 1; $level >= 0; --$level) {
            $levelCommand = $commands['C' . $level] ?? null;

            if ($levelCommand !== null) {
                return $levelCommand;
            }
        }

        return $this->command;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * Возвращает путь к файлу описания роли (относительно корня проекта).
     *
     * Если null — следует использовать неявную конвенцию {rolesDir}/{roleName}.ru.md.
     */
    public function getPromptFile(): ?string
    {
        return $this->promptFile;
    }

    public function getSystemPromptFile(): ?string
    {
        return $this->systemPromptFile;
    }

    /**
     * Возвращает конфигурацию fallback runner'а или null, если не задан.
     */
    public function getFallback(): ?FallbackConfigVo
    {
        return $this->fallback;
    }
}
