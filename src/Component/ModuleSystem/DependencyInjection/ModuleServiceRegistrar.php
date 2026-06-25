<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * PHAR-safe регистрация сервисов каталога через RecursiveDirectoryIterator.
 *
 * Generic-регистратор: применяется как к каталогам доменных модулей
 * (`src/Module/<Name>` — через {@see ModuleKernelTrait} / {@see ModuleCompilerPass}),
 * так и к каталогам Presentation-слоя (`apps/console/src/Module/.../Command`,
 * `.../EventSubscriber` — через {@see \TaskOrchestrator\Common\Kernel::build()}).
 * Имя сохранено как устоявшийся идентификатор исторической module-system;
 * generic-характер отражён в этом PHPDoc и в параметрах конструктора
 * (`serviceDir`/`serviceNamespace`, без привязки к ModuleInterface).
 *
 * Заменяет Symfony `resource:` auto-discovery (оператор `resource:`/
 * `exclude:` в `services.yaml`), который основан на {@see \Symfony\Component\Config\Resource\GlobResource}
 * и возвращает 0 файлов по `phar://` путям — фундаментальное ограничение
 * PHP stream-wrapper для glob по PHAR. Из-за этого в собранном PHAR сервисы
 * просто не регистрировались, и команды падали на этапе autowire.
 *
 * Подход: перечисление `*.php` через {@see RecursiveDirectoryIterator} (работает
 * по `phar://` — проверено эмпирически), FQCN-маппинг по PSR-4 namespace,
 * фильтрация несервисных типов (abstract/interface/trait/enum) и exclude-путей,
 * регистрация как autowired+autoconfigured Definition (опционально public).
 *
 * Порядок применения и explicit-wins (явное определение побеждает):
 *  • Для модулей вызывается из {@see ModuleCompilerPass} ПОСЛЕ загрузки модульного
 *    `services.yaml` — поэтому явные определения (aliases/параметры/аргументы/
 *    теги) всегда выигрывают: регистратор не перетирает уже заданные
 *    Definition/Alias (см. {@see register()}).
 *  • Для apps/console вызывается из {@see \TaskOrchestrator\Common\Kernel::build()}
 *    ДО загрузки `console_services.yaml`. Это безопасно: explicit defs в YAML
 *    (EventDispatcher/LockFactory) не пересекаются с FQCN каталогов apps/console,
 *    а при появлении такого explicit def поздняя загрузка YAML перетрёт
 *    определение регистратора — эквивалент explicit-wins.
 *
 * Container-wide autoconfiguration (теги интерфейсов и Command/EventSubscriber)
 * применяется к новым Definition единообразно и в dev, и в PHAR, независимо от
 * способа регистрации. Для Command/EventSubscriber теги добавляются
 * автоматически через FrameworkExtension::registerForAutoconfiguration().
 *
 * Сопоставление exclude-путей — по вхождению подстроки в относительный путь
 * файла (относительно {@see $serviceDir}).
 */
final readonly class ModuleServiceRegistrar
{
    public function __construct(
        private string $serviceDir,
        private string $serviceNamespace,
        /** @var list<string> относительные пути (от serviceDir) для исключения */
        private array $excludeRelativePaths,
        /** Делать новые Definition public (нужно для команд/подписчиков apps/console). */
        private bool $public = false,
    ) {
    }

    /**
     * Регистрирует все instantiable-классы модуля как сервисы контейнера.
     *
     * Идемпотентен к уже зарегистрированным сервисам: если Definition или Alias
     * для FQCN уже существует (явное определение из services.yaml) — он не
     * перетирается (explicit-wins).
     */
    public function register(ContainerBuilder $container): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->serviceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        // serviceDir без trailing-slash; +1 для отсечения разделителя каталогов.
        $prefixLen = strlen($this->serviceDir) + 1;

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), $prefixLen));

            // exclude по вхождению подстроки (как module-specific exclude-паттерны).
            if ($this->isExcluded($relative)) {
                continue;
            }

            $class = $this->serviceNamespace . '\\' . str_replace('/', '\\', substr($relative, 0, -4));

            // explicit-wins: уже определён в services.yaml → не трогаем.
            if ($container->hasDefinition($class) || $container->hasAlias($class)) {
                continue;
            }

            try {
                $reflection = $container->getReflectionClass($class);
            } catch (\ReflectionException) {
                // Файл есть, но класс не autoloadable (например, не совпадает FQCN
                // с PSR-4 или это не класс) — пропускаем.
                continue;
            }

            if ($reflection instanceof ReflectionClass && !$this->isInstantiable($reflection)) {
                continue;
            }

            $definition = new Definition($class);
            $definition->setAutowired(true);
            // autoconfigured=true применяет container-wide autoconfiguration
            // (теги интерфейсов, а также console.command / kernel.event_subscriber
            // для Command / EventSubscriber из FrameworkExtension) единообразно
            // для ресурса и регистратора.
            $definition->setAutoconfigured(true);
            if ($this->public) {
                $definition->setPublic(true);
            }
            $container->setDefinition($class, $definition);
        }
    }

    /**
     * @param string $relativePath путь файла относительно moduleDir (с прямыми слешами)
     */
    private function isExcluded(string $relativePath): bool
    {
        foreach ($this->excludeRelativePaths as $exclude) {
            if (str_contains($relativePath, $exclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Пригоден ли класс к регистрации как сервис: не abstract, не interface,
     * не trait, не enum и допускает создание экземпляра.
     *
     * Для PHP 8.4 enum проверяется явно через {@see ReflectionClass::isEnum()}
     * (ReflectionEnum), хотя isInstantiable() для enum и так возвращает false —
     * явная проверка делает намерение очевидным и устойчивым к версиям PHP.
     */
    private function isInstantiable(ReflectionClass $reflection): bool
    {
        return !$reflection->isAbstract()
            && !$reflection->isInterface()
            && !$reflection->isTrait()
            && !$reflection->isEnum()
            && $reflection->isInstantiable();
    }
}
