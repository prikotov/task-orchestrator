<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * PHAR-safe регистрация сервисов Presentation-слоя apps/console (консольные
 * команды и подписчики событий).
 *
 * Вынесено из {@see \TaskOrchestrator\Common\Kernel::build()}: Kernel остаётся
 * точкой сборки (порядок вызовов, compiler pass'ы), а механика раскладки
 * каталогов — здесь.
 *
 * Каталоги apps/console захардкожены здесь, а не в config/modules.php:
 * apps/console — это Presentation-слой конкретного CLI-приложения проекта
 * (не доменный модуль и не переиспользуемая часть библиотеки), поэтому он
 * не описывается ModuleInterface и не входит в реестр модулей.
 *
 * Регистрация выполняется в Kernel::build(), до загрузки console_services.yaml
 * (Symfony: prepareContainer→build → затем registerContainerConfiguration).
 * Это безопасно: explicit defs в YAML (EventDispatcher/LockFactory/FlockStore)
 * не пересекаются с FQCN этих каталогов. Теги `console.command` (Command) и
 * `kernel.event_subscriber` (EventSubscriber) добавляются автоматически через
 * container-wide registerForAutoconfiguration из FrameworkExtension — она
 * применяется на этапе compile единообразно для dev и PHAR. public=true
 * сохраняет прежнее поведение resource:-блоков (команды public).
 */
final readonly class ConsoleServicesRegistrar
{
    /**
     * Каталоги/неймспейсы Presentation-слоя apps/console (путь — относительно
     * package root).
     *
     * @var list<array{dir: string, namespace: string}>
     */
    private const PRESENTATION_REGISTRATIONS = [
        [
            'dir' => '/apps/console/src/Module/Orchestrator/Command',
            'namespace' => 'TaskOrchestrator\Console\Module\Orchestrator\Command',
        ],
        [
            'dir' => '/apps/console/src/Module/GitIdentity/Command',
            'namespace' => 'TaskOrchestrator\Console\Module\GitIdentity\Command',
        ],
        [
            'dir' => '/apps/console/src/Module/AgentRole/Command',
            'namespace' => 'TaskOrchestrator\Console\Module\AgentRole\Command',
        ],
        [
            'dir' => '/apps/console/src/Module/Orchestrator/EventSubscriber',
            'namespace' => 'TaskOrchestrator\Console\Module\Orchestrator\EventSubscriber',
        ],
    ];

    /**
     * @param string $packageDir каталог пакета (src/, apps/, config/), см.
     *                           PharRuntimePathsProviderInterface::packageDir()
     */
    public function __construct(
        private readonly string $packageDir,
    ) {
    }

    /**
     * Регистрирует сервисы всех каталогов Presentation-слоя через
     * {@see ModuleServiceRegistrar} (PHAR-safe: RecursiveDirectoryIterator
     * вместо Symfony GlobResource, который возвращает 0 файлов по phar://).
     */
    public function register(ContainerBuilder $container): void
    {
        foreach (self::PRESENTATION_REGISTRATIONS as $registration) {
            (new ModuleServiceRegistrar(
                serviceDir: $this->packageDir . $registration['dir'],
                serviceNamespace: $registration['namespace'],
                excludeRelativePaths: [],
                public: true,
            ))->register($container);
        }
    }
}
