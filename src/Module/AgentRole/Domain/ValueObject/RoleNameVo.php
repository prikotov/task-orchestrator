<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Имя роли агента.
 *
 * Выводится из имени файла роли (без расширения и локали), например
 * `backend_developer_levsha.ru.md` → `backend_developer_levsha`. Используется
 * как ключ в `config/chains.yaml` (`roles.<role>`) и идентификатор для
 * резолвинга skills роли.
 *
 * Допустимы строчные латинские буквы, цифры и знак подчёркивания (snake_case).
 */
final readonly class RoleNameVo
{
    private function __construct(
        private string $value,
    ) {}

    public static function createFromName(string $name): self
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Role name must not be empty.');
        }

        if (preg_match('/^[a-z0-9_]+$/u', $name) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Role name "%s" must be snake_case (lowercase a-z, 0-9, underscores only).', $name),
            );
        }

        if (str_starts_with($name, '_') || str_ends_with($name, '_')) {
            throw new InvalidArgumentException(
                sprintf('Role name "%s" must not start or end with an underscore.', $name),
            );
        }

        return new self($name);
    }

    /**
     * Выводит имя роли из имени файла роли.
     *
     * Совпадает с логикой watch-subagent.sh derive_role_name: убирает расширение
     * `.md` и суффикс локали из двух букв (например, `.ru`).
     *
     * Пример: `backend_developer_levsha.ru.md` → `backend_developer_levsha`.
     *
     * @param string $fileName basename файла роли (с расширением)
     */
    public static function createFromFileName(string $fileName): self
    {
        $name = basename($fileName);

        if (str_ends_with($name, '.md')) {
            $name = substr($name, 0, -3);
        }

        // Суффикс локали: две строчные буквы перед убранным расширением (.ru, .en).
        if (preg_match('/\.[a-z]{2}$/', $name)) {
            $name = substr($name, 0, -3);
        }

        return self::createFromName($name);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
