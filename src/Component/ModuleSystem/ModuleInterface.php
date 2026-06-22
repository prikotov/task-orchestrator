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
 * Resource/config/services.yaml модуля.
 *
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass
 */
interface ModuleInterface
{
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
}
