<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem;

/**
 * Контракт модуля приложения.
 *
 * Каждый доменный модуль (src/Module/<Name>/) реализует этот интерфейс и
 * объявляет пути к своим ресурсам. Реестр модулей config/modules.php
 * перебирает классы, реализующие интерфейс, а Kernel (через ModuleKernelTrait)
 * регистрирует для каждого модуля ModuleCompilerPass, который подгружает
 * Resource/config/services.yaml модуля и запускает PHAR-safe auto-discovery
 * сервисов через {@see \TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleServiceRegistrar}.
 *
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleServiceRegistrar
 */
interface ModuleInterface
{
    /**
     * Стандартный набор exclude-путей для несервисных типов DDD-слоёв.
     *
     * Соответствует конвенции docs/conventions/modules/configuration.md (правило 10):
     * из auto-discovery исключаются сущности, перечисления, объекты-значения,
     * DTO, исключения, события (event payload), каталог ресурсов и класс
     * модуля. Модули композируют свой список из этого базового набора и
     * module-specific excludes (см. {@see getServiceExcludePaths()}).
     *
     * @var list<string>
     */
    public const array DEFAULT_SERVICE_EXCLUDE_PATHS = [
        'Domain/Dto/',
        'Domain/Entity/',
        'Domain/Enum/',
        'Domain/Exception/',
        'Domain/ValueObject/',
        'Application/Dto/',
        'Application/Enum/',
        'Application/Event/',
        'Application/Exception/',
        'Resource/',
    ];

    /**
     * Абсолютный путь к каталогу модуля (обычно __DIR__).
     *
     * @psalm-suppress UnusedMethod
     */
    public function getModuleDir(): string;

    /**
     * Абсолютный путь к каталогу конфигурации сервисов модуля
     * (обычно getModuleDir() . '/Resource/config').
     */
    public function getModuleConfigPath(): string;

    /**
     * PSR-4 namespace-префикс сервисов модуля (для FQCN-маппинга при PHAR-safe
     * auto-discovery через ModuleServiceRegistrar). Например:
     * 'TaskOrchestrator\Common\Module\GitIdentity'.
     *
     * Должен совпадать с PSR-4 autoload-префиксом каталога getModuleDir().
     */
    public function getServiceNamespace(): string;

    /**
     * Относительные пути (от getModuleDir()) для исключения из auto-discovery.
     *
     * Соответствуют прежним exclude-паттернам из Resource/config/services.yaml.
     * Исключаются несервисные типы DDD-слоёв (Entity/Enum/ValueObject/Dto/
     * Exception/Event), каталог ресурсов, класс самого модуля и любые
     * дополнительные payload/value-каталоги или классы модуля.
     * Сопоставление — по вхождению подстроки в относительный путь файла.
     *
     * @return list<string>
     */
    public function getServiceExcludePaths(): array;
}
