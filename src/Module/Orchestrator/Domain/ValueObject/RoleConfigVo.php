<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object конфигурации роли для оркестрации.
 *
 * Описывает per-role настройки: полная CLI-команда, таймаут, путь к файлу описания роли,
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
     * @param FallbackConfigVo|null $fallback конфигурация fallback runner'а.
     *        Определяет альтернативную CLI-команду для запуска при ошибке основного runner'а.
     *        Первый элемент fallback.command — имя runner'а в registry.
     *        Если null — fallback не сконфигурирован, при ошибке шаг завершается с ошибкой.
     */
    public function __construct(
        private array $command = [],
        private ?int $timeout = null,
        private ?string $promptFile = null,
        private ?FallbackConfigVo $fallback = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
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

    /**
     * Возвращает конфигурацию fallback runner'а или null, если не задан.
     */
    public function getFallback(): ?FallbackConfigVo
    {
        return $this->fallback;
    }
}
