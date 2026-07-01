<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Имя skill (навыка) по стандарту Agent Skills.
 *
 * Правила (https://agentskills.io/specification):
 * - 1-64 символа;
 * - только строчные латинские буквы, цифры и дефисы;
 * - без дефиса в начале/конце и без подряд идущих дефисов.
 *
 * Имя извлекается из frontmatter поля `name` файла SKILL.md либо выводится из
 * имени родительского каталога (как делают pi и codex).
 */
final readonly class SkillNameVo
{
    private const int MAX_LENGTH = 64;

    private function __construct(
        private string $value,
    ) {}

    public static function createFromName(string $name): self
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Skill name must not be empty.');
        }

        if (strlen($name) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Skill name exceeds %d characters (%d).', self::MAX_LENGTH, strlen($name)),
            );
        }

        if (preg_match('/[^a-z0-9-]/u', $name) === 1) {
            throw new InvalidArgumentException(
                sprintf('Skill name "%s" contains invalid characters (only lowercase a-z, 0-9, hyphens allowed).', $name),
            );
        }

        if (str_starts_with($name, '-') || str_ends_with($name, '-') || str_contains($name, '--')) {
            throw new InvalidArgumentException(
                sprintf('Skill name "%s" must not start or end with a hyphen and must not contain consecutive hyphens.', $name),
            );
        }

        return new self($name);
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
