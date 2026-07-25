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
 * Покрывает три ключевых свойства ядра {@see Kernel}:
 *  - параметры task_orchestrator.* разрешаются в standalone-режиме (projectRoot =
 *    packageRoot);
 *  - dual-context: при передаче projectRoot хост-проекта base_path/roles_dir
 *    уходят в хост, а package_dir/kernel.project_dir остаются на пакете (config/,
 *    bundles.php, modules.php грузятся из пакета);
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
    public function frameworkDefaultLocaleFallsBackToEnWhenAppLocaleUnset(): void
    {
        // Regression для '%env(default:en:APP_LOCALE)%': Symfony-процессор
        // `default:fallback:VAR` трактует fallback как имя container-параметра, а не
        // литерал, поэтому при чтении локали бросало "parameter 'en' not found".
        // Фикс — idiomatic env-default: parameters.env(APP_LOCALE): en.
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $restore = $this->isolateEnvVar('APP_LOCALE', null);

        try {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            $container = $kernel->getContainer();
            // Не задано → дефолт 'en'.
            self::assertSame('en', $container->getParameter('kernel.default_locale'));
            // task_orchestrator.locale (Kernel, из $_SERVER напрямую) — тот же дефолт 'en'.
            self::assertSame('en', $container->getParameter('task_orchestrator.locale'));
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
    public function frameworkDefaultLocaleFollowsAppLocaleEnv(): void
    {
        $cacheDir = $this->createIsolatedCacheDir();
        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $restore = $this->isolateEnvVar('APP_LOCALE', 'ru');

        try {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            self::assertSame('ru', $kernel->getContainer()->getParameter('kernel.default_locale'));
            // task_orchestrator.locale следует APP_LOCALE (lower-case).
            self::assertSame('ru', $kernel->getContainer()->getParameter('task_orchestrator.locale'));
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            $restore();
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
