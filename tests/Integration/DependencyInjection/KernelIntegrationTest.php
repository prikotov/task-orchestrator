<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Kernel;

/**
 * Интеграционная проверка сборки контейнера через Symfony Kernel.
 *
 * Покрывает ключевые свойства ядра {@see Kernel}:
 *  - параметры task_orchestrator.* разрешаются в standalone-режиме (projectRoot =
 *    packageRoot);
 *  - dual-context: при передаче projectRoot хост-проекта base_path/roles_dir
 *    уходят в хост, а package_dir/kernel.project_dir остаются на пакете (config/,
 *    bundles.php, modules.php грузятся из пакета);
 *  - кеш Composer-host изолирован от приложения и разделён по версии пакета;
 *  - локаль AI-ролей (task_orchestrator.locale, env TASK_ORCHESTRATOR_LOCALE) —
 *    runtime env-параметр: автоматический поиск при отсутствии значения,
 *    независимость от APP_LOCALE и от локали Symfony-переводчика
 *    (kernel.default_locale), смена локали без
 *    очистки кеша при том же корне кеша;
 *  - Resource PHP-файлы (bridge модуля AgentRunner) исключены из auto-discovery
 *    сервисов (resource/exclude в config/services.yaml).
 *
 * Заменяет прежний TaskOrchestratorExtensionTest: после перехода на Kernel
 * TaskOrchestratorExtension удалён, поведение проверяется через реальное ядро.
 */
#[CoversClass(Kernel::class)]
final class KernelIntegrationTest extends TestCase
{
    private string $packageRoot;

    #[\Override]
    protected function setUp(): void
    {
        $this->packageRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function standaloneResolvesParametersToPackageRoot(): void
    {
        $kernel = new Kernel('test', false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();

            self::assertSame($this->packageRoot, $container->getParameter('task_orchestrator.package_dir'));
            self::assertSame($this->packageRoot, $container->getParameter('task_orchestrator.base_path'));
            self::assertFalse($container->getParameter('task_orchestrator.is_phar'));
            self::assertSame($this->packageRoot, $container->getParameter('kernel.project_dir'));
            self::assertSame(
                $this->packageRoot . '/docs/agents/roles/team',
                $container->getParameter('task_orchestrator.roles_dir'),
            );
            self::assertSame(
                $this->packageRoot . '/var/sessions',
                $container->getParameter('task_orchestrator.chains_session_dir'),
            );
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function sourceCheckoutResolvesAppVersionToNonReleaseMarker(): void
    {
        // Arrange: source checkout без релизной версии — Composer даёт ветку
        // `dev-*` либо `1.0.0+no-version-set`, что не является точной SemVer.
        // Изолируем кэш контейнера: параметр app.version вычисляется при
        // компиляции и запекается, поэтому без свежей компиляции тест вернул бы
        // закэшированное значение.
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;

        try {
            // Act
            $kernel = new Kernel('test', false);
            $kernel->boot();

            // Assert: параметр app.version равен non-release marker `dev`,
            // а НЕ нормализованному Composer-значению (`1.0.0.0`) либо ветке.
            self::assertSame('dev', $kernel->getContainer()->getParameter('app.version'));
            $kernel->shutdown();
        } finally {
            unset($_SERVER['APP_CACHE_DIR']);
            $this->removeDirectory($cacheDir);
        }
    }

    #[Test]
    public function explicitReleaseVersionOverridesResolvedAppVersion(): void
    {
        // Arrange: процесс сборки инъецирует точную SemVer release tag.
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $_SERVER['APP_RELEASE_VERSION'] = '0.2.1';

        try {
            // Act
            $kernel = new Kernel('test', false);
            $kernel->boot();

            // Assert
            self::assertSame('0.2.1', $kernel->getContainer()->getParameter('app.version'));
            $kernel->shutdown();
        } finally {
            unset($_SERVER['APP_CACHE_DIR'], $_SERVER['APP_RELEASE_VERSION']);
            $this->removeDirectory($cacheDir);
        }
    }

    #[Test]
    public function agentRoleLocaleUsesAutomaticSearchWhenTaskOrchestratorLocaleUnset(): void
    {
        // Локаль AI-ролей (env TASK_ORCHESTRATOR_LOCALE) не задана →
        // пустая локаль включает автоматический поиск доступного role file.
        // Ранее regression для '%env(default:en:APP_LOCALE)%': Symfony-процессор
        // `default:fallback:VAR` трактовал fallback как имя container-параметра,
        // из-за чего чтение локали бросало "parameter 'en' not found". Нормализация
        // default теперь в TaskOrchestratorLocaleEnvVarProcessor (заменил
        // parameters.env(APP_LOCALE): en в translation.yaml).
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $restore = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', null);

        try {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            $container = $kernel->getContainer();
            // Локаль AI-ролей не задана → автоматический поиск.
            self::assertSame('', $container->getParameter('task_orchestrator.locale'));
            // Локаль Symfony-переводчика — независимая настройка (default `en`).
            self::assertSame('en', $container->getParameter('kernel.default_locale'));
            // translator конструируется, читая локаль — раньше падал здесь.
            self::assertNotNull($container->get('translator'));
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            $restore();
            $this->removeDirectory($cacheDir);
        }
    }

    #[Test]
    public function agentRoleLocaleFollowsTaskOrchestratorLocaleEnv(): void
    {
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $restore = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', 'ru');

        try {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            // Локаль AI-ролей следует TASK_ORCHESTRATOR_LOCALE (lower-case).
            self::assertSame('ru', $kernel->getContainer()->getParameter('task_orchestrator.locale'));
            // Независимость: локаль Symfony-переводчика НЕ следует за локалью
            // AI-ролей (framework.default_locale — отдельная настройка Symfony).
            self::assertSame('en', $kernel->getContainer()->getParameter('kernel.default_locale'));
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            $restore();
            $this->removeDirectory($cacheDir);
        }
    }

    #[Test]
    public function appLocaleDoesNotAffectAgentRoleLocale(): void
    {
        // Regression скрытому fallback (резервному переходу) на APP_LOCALE:
        // локаль host-проекта APP_LOCALE не является контрактом task-orchestrator
        // и не влияет ни на локаль AI-ролей, ни на локаль Symfony-переводчика.
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $restoreTaskLocale = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', null);
        $restoreAppLocale = $this->isolateEnvVar('APP_LOCALE', 'ru');

        try {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            // Даже при APP_LOCALE=ru локаль AI-ролей остаётся в режиме автоматического поиска.
            self::assertSame('', $kernel->getContainer()->getParameter('task_orchestrator.locale'));
            // APP_LOCALE больше не влияет и на kernel.default_locale.
            self::assertSame('en', $kernel->getContainer()->getParameter('kernel.default_locale'));
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            $restoreAppLocale();
            $restoreTaskLocale();
            $this->removeDirectory($cacheDir);
        }
    }

    #[Test]
    public function agentRoleLocaleSwitchesOnNextBootWithoutCacheClearing(): void
    {
        // Stale-cache regression: task_orchestrator.locale — динамический
        // env-параметр (%env()%), поэтому смена TASK_ORCHESTRATOR_LOCALE
        // применяется при следующем запуске на ТОМ ЖЕ корне кеша — без ручной
        // очистки скомпилированного контейнера (ранее значение запекалось в кеш
        // и не менялось до удаления var/cache).
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;

        try {
            // Первый запуск: компилирует и сохраняет контейнер с локалью en.
            $restoreEn = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', 'en');
            $first = new Kernel('test', false);
            $first->boot();
            self::assertSame('en', $first->getContainer()->getParameter('task_orchestrator.locale'));
            self::assertFileExists($cacheDir . '/test');
            $first->shutdown();
            $restoreEn();

            // Кеш между запусками не удаляется.

            // Второй запуск: тот же корень кеша, локаль ru — без очистки кеша.
            $restoreRu = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', 'ru');
            try {
                $second = new Kernel('test', false);
                $second->boot();
                self::assertSame('ru', $second->getContainer()->getParameter('task_orchestrator.locale'));
                $second->shutdown();
            } finally {
                $restoreRu();
            }
        } finally {
            unset($_SERVER['APP_CACHE_DIR']);
            $this->removeDirectory($cacheDir);
        }
    }

    /**
     * Изолирует env-переменную на время теста и возвращает restore-callback
     * (вызвать в finally). $value = null — переменная снимается.
     */
    private function isolateEnvVar(string $name, ?string $value): \Closure
    {
        $hadServer = \array_key_exists($name, $_SERVER);
        $server = $_SERVER[$name] ?? null;
        $hadEnv = \array_key_exists($name, $_ENV);
        $env = $_ENV[$name] ?? null;
        $getenv = \getenv($name); // false, когда переменная не задана

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            \putenv($name);
        } else {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            \putenv($name . '=' . $value);
        }

        return static function () use ($name, $hadServer, $server, $hadEnv, $env, $getenv): void {
            if ($hadServer) {
                $_SERVER[$name] = $server;
            } else {
                unset($_SERVER[$name]);
            }
            if ($hadEnv) {
                $_ENV[$name] = $env;
            } else {
                unset($_ENV[$name]);
            }
            if ($getenv !== false) {
                \putenv($name . '=' . $getenv);
            } else {
                \putenv($name);
            }
        };
    }

    private function createIsolatedCacheDir(): string
    {
        return sys_get_temp_dir() . '/to-kernel-cache-' . bin2hex(random_bytes(6));
    }

    #[Test]
    public function composerHostCacheIsIsolatedAndChangesWithReleaseVersion(): void
    {
        // Arrange
        $hostRoot = sys_get_temp_dir() . '/to-kernel-cache-host-' . bin2hex(random_bytes(6));
        $restoreCacheDir = $this->isolateEnvVar('APP_CACHE_DIR', null);
        $restoreReleaseVersion = $this->isolateEnvVar('APP_RELEASE_VERSION', '0.5.0');

        try {
            // Act
            $firstCacheDir = (new Kernel('prod', false, $hostRoot))->getCacheDir();
            $_SERVER['APP_RELEASE_VERSION'] = '0.5.1';
            $_ENV['APP_RELEASE_VERSION'] = '0.5.1';
            putenv('APP_RELEASE_VERSION=0.5.1');
            $nextCacheDir = (new Kernel('prod', false, $hostRoot))->getCacheDir();

            // Assert
            self::assertSame(
                $hostRoot . '/var/cache/task-orchestrator/0.5.0/prod',
                $firstCacheDir,
            );
            self::assertSame(
                $hostRoot . '/var/cache/task-orchestrator/0.5.1/prod',
                $nextCacheDir,
            );
            self::assertNotSame($hostRoot . '/var/cache/prod', $firstCacheDir);
        } finally {
            $restoreReleaseVersion();
            $restoreCacheDir();
        }
    }

    #[Test]
    public function dualContextResolvesHostResourcesAndPackageConfig(): void
    {
        // Имитируем vendor-binary: хост-проект имеет собственные роли, но без
        // config/chains.yaml (должен отпасть в пакетный fallback).
        $hostRoot = sys_get_temp_dir() . '/to-kernel-host-' . bin2hex(random_bytes(6));
        mkdir($hostRoot . '/docs/agents/roles/team', 0777, true);
        mkdir($hostRoot . '/config', 0777, true);

        $kernel = new Kernel('test', false, $hostRoot);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();

            // Хост-ресурсы (roles, base_path, sessions) уходят в хост-проект.
            self::assertSame($hostRoot, $container->getParameter('task_orchestrator.base_path'));
            self::assertSame(
                $hostRoot . '/docs/agents/roles/team',
                $container->getParameter('task_orchestrator.roles_dir'),
            );
            self::assertSame($hostRoot . '/var/sessions', $container->getParameter('task_orchestrator.chains_session_dir'));

            // Пакетные ресурсы (config, kernel.project_dir) остаются на пакете.
            self::assertSame($this->packageRoot, $container->getParameter('task_orchestrator.package_dir'));
            self::assertSame($this->packageRoot, $container->getParameter('kernel.project_dir'));

            // chains.yaml отсутствует в хосте — fallback на пакетный.
            self::assertSame(
                $this->packageRoot . '/config/chains.yaml',
                $container->getParameter('task_orchestrator.chains_yaml'),
            );
        } finally {
            $kernel->shutdown();
            $this->removeDirectory($hostRoot);
        }
    }

    #[Test]
    public function resourcePhpFilesAreExcludedFromServiceDiscovery(): void
    {
        // Bridge — PHP-файл в Resources модуля AgentRunner. Он не должен
        // регистрироваться как сервис (resource/exclude в config/services.yaml).
        // После компиляции исключённые определения недоступны через контейнер.
        $kernel = new Kernel('test', false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $bridgeId = 'TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\Resources\bridge';

            self::assertFalse($container->has($bridgeId));
        } finally {
            $kernel->shutdown();
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
