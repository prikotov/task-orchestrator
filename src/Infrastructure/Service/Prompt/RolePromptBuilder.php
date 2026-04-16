<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\Prompt;

use TasK\Orchestrator\Domain\Exception\RoleNotFoundException;
use TasK\Orchestrator\Domain\Service\Prompt\PromptProviderInterface;
use Override;

/**
 * Реализация PromptProviderInterface — чтение .md файлов ролей из директории.
 *
 * Путь к директории — параметр конструктора (TasK-specific).
 */
final class RolePromptBuilder implements PromptProviderInterface
{
    private const DEFAULT_LOCALE = 'ru';

    private string $rolesDir;

    private string $basePath;

    /** @var array<string, string>|null */
    private ?array $cache = null;

    /** @var array<string, string>|null */
    private ?array $descriptions = null;

    public function __construct(string $rolesDir, string $basePath)
    {
        $this->rolesDir = $rolesDir;
        $this->basePath = rtrim($basePath, '/');
    }

    #[Override]
    public function getPrompt(string $role): string
    {
        $this->loadCache();

        if (!isset($this->cache[$role])) {
            throw new RoleNotFoundException($role);
        }

        return $this->cache[$role];
    }

    #[Override]
    public function getPromptFilePath(string $role): string
    {
        $this->loadCache();

        if (!isset($this->cache[$role])) {
            throw new RoleNotFoundException($role);
        }

        $absolute = $this->buildDefaultLocaleFilePath($role);

        // Относительный путь от корня проекта — агент запускается из корня
        if (str_starts_with($absolute, $this->basePath . '/')) {
            return substr($absolute, strlen($this->basePath) + 1);
        }

        return $absolute;
    }

    #[Override]
    public function roleExists(string $role): bool
    {
        $this->loadCache();

        return isset($this->cache[$role]);
    }

    #[Override]
    public function getAvailableRoles(): array
    {
        $this->loadCache();

        return $this->descriptions ?? [];
    }

    /**
     * Загружает кэш ролей из файловой системы (ленивая загрузка).
     */
    private function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        $this->descriptions = [];

        $pattern = rtrim($this->rolesDir, '/') . '/*.' . self::DEFAULT_LOCALE . '.md';
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $roleName = preg_replace('/\.' . self::DEFAULT_LOCALE . '$/', '', pathinfo($file, PATHINFO_FILENAME)) ?? pathinfo($file, PATHINFO_FILENAME);
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $this->cache[$roleName] = $content;
            $this->descriptions[$roleName] = $this->extractDescription($content);
        }
    }

    /**
     * Извлекает описание роли из первой строки markdown (заголовок #).
     */
    private function extractDescription(string $content): string
    {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }

        return '';
    }

    private function buildDefaultLocaleFilePath(string $role): string
    {
        return rtrim($this->rolesDir, '/') . '/' . $role . '.' . self::DEFAULT_LOCALE . '.md';
    }
}
