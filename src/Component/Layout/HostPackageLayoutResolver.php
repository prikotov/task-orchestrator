<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\Layout;

use InvalidArgumentException;

/**
 * Чистый резолвер runtime layout-ресурсов: host-проект → package root.
 *
 * Dual-context layout (разметка путей двух контекстов): ресурс (roles_dir,
 * skills_dir, chains_yaml) разрешается в host-путь, если ресурс уже существует
 * в host-проекте, иначе — в package-путь (fallback на упакованные ресурсы
 * пакета/PHAR). При отсутствии ОБОИХ кандидатов бросается явное
 * {@see LayoutResourceNotFoundException}: возврат несуществующего пути запрещён.
 *
 * Компонент намеренно без Symfony-зависимостей (только is_dir/is_file над
 * переданными путями — конвенция core-patterns/component.md): файловая политика
 * выбора host/package изолирована от конфигурации контейнера и покрыта
 * собственными unit-тестами. Тонкая привязка к Symfony-контейнеру —
 * {@see TaskOrchestratorLayoutEnvVarProcessor}.
 *
 * Вызов выполняется на каждом запуске приложения (runtime): компонент решает
 * дефект QA-3 — ресурсы host-проекта, созданные после первого запуска PHAR,
 * подхватываются при следующем запуске без ручной очистки кеша контейнера.
 *
 * Класс намеренно НЕ регистрируется сервисом контейнера и создаётся через
 * `new` внутри {@see TaskOrchestratorLayoutEnvVarProcessor}: это чистый
 * резолвер без Symfony-зависимостей (правка архитектора №4) и внутренность
 * процессора, а не публичная точка DI.
 */
final readonly class HostPackageLayoutResolver
{
    /** Относительный путь каталога role-файлов в корне host-проекта/пакета. */
    public const string ROLES_DIR_RELATIVE_PATH = 'docs/agents/roles/team';

    /** Относительный путь каталога skills в корне host-проекта/пакета. */
    public const string SKILLS_DIR_RELATIVE_PATH = 'docs/agents/skills';

    /** Относительный путь YAML-конфигурации цепочек в корне host-проекта/пакета. */
    public const string CHAINS_YAML_RELATIVE_PATH = 'config/chains.yaml';

    /**
     * @param string $hostRoot    корень host-проекта (источник ролей/skills/цепочек
     *                            host-проекта; в standalone-режиме совпадает с package root)
     * @param string $packageRoot package root — корень пакета (src/, config/, docs/),
     *                            в PHAR — phar://-корень архива
     */
    public function __construct(
        private readonly string $hostRoot,
        private readonly string $packageRoot,
    ) {
        if ($this->hostRoot === '' || $this->packageRoot === '') {
            throw new InvalidArgumentException(
                'Корни host-проекта и пакета должны быть непустыми путями.',
            );
        }
    }

    /**
     * Каталог role-файлов (roles_dir): host-проект при наличии, иначе package.
     */
    public function resolveRolesDir(): string
    {
        return $this->resolveDirectory(self::ROLES_DIR_RELATIVE_PATH);
    }

    /**
     * Каталог skills (skills_dir): host-проект при наличии, иначе package.
     */
    public function resolveSkillsDir(): string
    {
        return $this->resolveDirectory(self::SKILLS_DIR_RELATIVE_PATH);
    }

    /**
     * Файл YAML-конфигурации цепочек (chains_yaml): host при наличии, иначе package.
     */
    public function resolveChainsYaml(): string
    {
        return $this->resolveFile(self::CHAINS_YAML_RELATIVE_PATH);
    }

    /**
     * Каталог по относительному пути: host при наличии, иначе package,
     * иначе явное исключение (ни один кандидат не существует).
     */
    private function resolveDirectory(string $relativePath): string
    {
        $hostPath = $this->hostPath($relativePath);
        if (is_dir($hostPath)) {
            return $hostPath;
        }

        $packagePath = $this->packagePath($relativePath);
        if (is_dir($packagePath)) {
            return $packagePath;
        }

        throw new LayoutResourceNotFoundException($relativePath);
    }

    /**
     * Файл по относительному пути: host при наличии, иначе package,
     * иначе явное исключение (ни один кандидат не существует).
     */
    private function resolveFile(string $relativePath): string
    {
        $hostPath = $this->hostPath($relativePath);
        if (is_file($hostPath)) {
            return $hostPath;
        }

        $packagePath = $this->packagePath($relativePath);
        if (is_file($packagePath)) {
            return $packagePath;
        }

        throw new LayoutResourceNotFoundException($relativePath);
    }

    private function hostPath(string $relativePath): string
    {
        return self::join($this->hostRoot, $relativePath);
    }

    private function packagePath(string $relativePath): string
    {
        return self::join($this->packageRoot, $relativePath);
    }

    /**
     * Склейка корня и относительного пути с нормализацией граничных слэшей:
     * trailing slash корня и ведущий слэш пути не дают двойной разделитель
     * в результате.
     */
    private static function join(string $root, string $relativePath): string
    {
        return rtrim($root, '/\\') . '/' . ltrim($relativePath, '/');
    }
}
