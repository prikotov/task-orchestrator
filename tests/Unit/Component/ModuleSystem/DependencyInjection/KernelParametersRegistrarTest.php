<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\KernelParametersRegistrar;

/**
 * Unit-проверка сборки параметров ядра task_orchestrator.*.
 *
 * Покрывает: состав параметров (контракт services.yaml), env-плейсхолдеры
 * phar/layout/locale-параметров (не запекаются при компиляции), plain-значения
 * от конструктора и производную chains_session_dir.
 */
#[CoversClass(KernelParametersRegistrar::class)]
final class KernelParametersRegistrarTest extends TestCase
{
    #[Test]
    public function parametersExposeAllTaskOrchestratorKeys(): void
    {
        $registrar = new KernelParametersRegistrar(
            projectRoot: '/host/project',
            isPhar: false,
            platformFamily: 'Linux',
            version: '1.2.3',
        );

        $parameters = $registrar->parameters();

        self::assertSame(
            [
                'task_orchestrator.package_dir',
                'task_orchestrator.phar_path',
                'task_orchestrator.base_path',
                'task_orchestrator.is_phar',
                'task_orchestrator.chains_session_dir',
                'task_orchestrator.platform_family',
                'task_orchestrator.locale',
                'task_orchestrator.roles_dir',
                'task_orchestrator.skills_dir',
                'task_orchestrator.skills_dirs',
                'task_orchestrator.chains_yaml',
                'app.version',
            ],
            array_keys($parameters),
        );
    }

    #[Test]
    public function parametersKeepRuntimeEnvPlaceholdersDynamic(): void
    {
        $registrar = new KernelParametersRegistrar(
            projectRoot: '/host/project',
            isPhar: false,
            platformFamily: 'Linux',
            version: '1.2.3',
        );

        $parameters = $registrar->parameters();

        // Phar-зависимые пути, локаль AI-ролей и layout-пути НЕ запекаются:
        // остаются env-плейсхолдерами и резолвятся в runtime env-процессорами.
        self::assertSame('%env(task_orchestrator_phar:package_dir)%', $parameters['task_orchestrator.package_dir']);
        self::assertSame('%env(task_orchestrator_phar:phar_path)%', $parameters['task_orchestrator.phar_path']);
        self::assertSame('%env(task_orchestrator_locale:TASK_ORCHESTRATOR_LOCALE)%', $parameters['task_orchestrator.locale']);
        self::assertSame('%env(task_orchestrator_layout:roles_dir)%', $parameters['task_orchestrator.roles_dir']);
        self::assertSame('%env(task_orchestrator_layout:skills_dir)%', $parameters['task_orchestrator.skills_dir']);
        self::assertSame('%env(task_orchestrator_layout:skills_dirs)%', $parameters['task_orchestrator.skills_dirs']);
        self::assertSame('%env(task_orchestrator_layout:chains_yaml)%', $parameters['task_orchestrator.chains_yaml']);
    }

    #[Test]
    public function parametersDerivePlainValuesFromConstructor(): void
    {
        $registrar = new KernelParametersRegistrar(
            projectRoot: '/host/project',
            isPhar: false,
            platformFamily: 'Linux',
            version: '1.2.3',
        );

        $parameters = $registrar->parameters();

        self::assertSame('/host/project', $parameters['task_orchestrator.base_path']);
        self::assertFalse($parameters['task_orchestrator.is_phar']);
        self::assertSame('/host/project/var/sessions', $parameters['task_orchestrator.chains_session_dir']);
        self::assertSame('Linux', $parameters['task_orchestrator.platform_family']);
        self::assertSame('1.2.3', $parameters['app.version']);
    }

    #[Test]
    public function parametersExposePharFlagWhenRunningFromPhar(): void
    {
        $registrar = new KernelParametersRegistrar(
            projectRoot: '/host/project',
            isPhar: true,
            platformFamily: 'Linux',
            version: '1.2.3',
        );

        $parameters = $registrar->parameters();

        self::assertTrue($parameters['task_orchestrator.is_phar']);
    }
}
