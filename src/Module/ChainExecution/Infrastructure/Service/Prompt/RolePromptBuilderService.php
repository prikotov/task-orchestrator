<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Prompt;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\RoleNotFoundException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Prompt\RolePromptBuilderServiceInterface;

/**
 * Реализация PromptProviderInterface — чтение .md файлов ролей из директории.
 *
 * Путь к директории — параметр конструктора (specific to the bundle).
 *
 * Локаль файла роли берётся из DI-параметра `task_orchestrator.locale` (env
 * APP_LOCALE). Резолвинг файла эквивалентен логике {@see \TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemLocateRoleFileService}
 * (модуль AgentRole) на fallback-цепочке `<role>.<locale>.md` → `<role>.md`
 * (нейтральный) → любой доступный перевод `<role>.*.md` — единый env APP_LOCALE
 * управляет выбором role-файла во всех точках приложения.
 *
 * ⚠️ Эквивалентность гарантируется для двухбуквенных строчных локалей;
 * формальное расхождение на шаге 3 — см. {@see selectFileForRole()} и
 * {@see deriveFileLocale()}.
 */
final class RolePromptBuilderService implements RolePromptBuilderServiceInterface
{
    private string $rolesDir;

    private string $basePath;

    private string $locale;

    /** @var array<string, string>|null role-name => содержимое выбранного файла */
    private ?array $cache = null;

    /** @var array<string, string>|null role-name => описание роли (первая строка-заголовок) */
    private ?array $descriptions = null;

    /** @var array<string, string>|null role-name => абсолютный путь выбранного файла */
    private ?array $filePaths = null;

    public function __construct(string $rolesDir, string $basePath, string $locale)
    {
        $this->rolesDir = $rolesDir;
        $this->basePath = rtrim($basePath, '/');
        $this->locale = strtolower($locale);
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

        if (!isset($this->filePaths[$role])) {
            throw new RoleNotFoundException($role);
        }

        $absolute = $this->filePaths[$role];

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
     * Список доступных ролей включает ВСЕ роли каталога (любой перевод или
     * нейтральный файл), а не только файлы под фиксированную локаль.
     */
    private function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        $this->descriptions = [];
        $this->filePaths = [];

        /** @var array<string, list<array{path: string, locale: string|null}>> $byRole */
        $byRole = [];

        $files = glob(rtrim($this->rolesDir, '/') . '/*.md');
        if ($files === false) {
            return;
        }

        // glob на Linux возвращает файлы отсортированными по имени — это
        // детерминирует порядок кандидатов на этапе fallback «любой перевод».
        foreach ($files as $file) {
            $role = $this->deriveRoleName($file);
            $byRole[$role][] = ['path' => $file, 'locale' => $this->deriveFileLocale($file)];
        }

        foreach ($byRole as $role => $candidates) {
            $selected = $this->selectFileForRole($candidates, $this->locale);
            $content = file_get_contents($selected);
            if ($content === false) {
                continue;
            }

            $this->cache[$role] = $content;
            $this->descriptions[$role] = $this->extractDescription($content);
            $this->filePaths[$role] = $selected;
        }
    }

    /**
     * Выбирает файл роли по fallback-цепочке, эквивалентной
     * {@see \TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemLocateRoleFileService}
     * в рамках проектной схемы именования локалей из двух строчных латинских букв
     * (формальное расхождение на шаге 3 описано в PHPDoc класса):
     *   1) `<role>.<locale>.md` — текущая локаль приложения;
     *   2) `<role>.md` — локаль-нейтральный файл;
     *   3) первый по glob-порядку `<role>.*.md` — любой доступный перевод.
     *
     * @param list<array{path: string, locale: string|null}> $candidates
     */
    private function selectFileForRole(array $candidates, string $locale): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate['locale'] === $locale) {
                return $candidate['path'];
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate['locale'] === null) {
                return $candidate['path'];
            }
        }

        // Candidates непуст (получены из группировки по реально существующим
        // файлам) — берём первый по glob-порядку.
        return $candidates[0]['path'];
    }

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

    /**
     * Дублирует логику модуля AgentRole (`RoleNameVo::createFromFileName`):
     * осознанное решение — модули изолированы по DDD и не должны зависеть
     * друг от друга.
     */
    private function deriveRoleName(string $fileName): string
    {
        $name = basename($fileName);

        if (str_ends_with($name, '.md')) {
            $name = substr($name, 0, -3);
        }

        // Суффикс локали: две строчные буквы перед убранным расширением (.ru, .en).
        if (preg_match('/\.[a-z]{2}$/', $name)) {
            $name = substr($name, 0, -3);
        }

        return $name;
    }

    private function deriveFileLocale(string $fileName): ?string
    {
        $name = basename($fileName);

        if (str_ends_with($name, '.md')) {
            $name = substr($name, 0, -3);
        }

        if (preg_match('/\.([a-z]{2})$/', $name, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
