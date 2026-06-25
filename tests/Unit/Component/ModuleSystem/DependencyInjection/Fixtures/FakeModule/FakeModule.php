<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule;

/**
 * Фикстура «класс модуля»: исключается из auto-discovery по совпадению имени
 * файла (FakeModule.php) с exclude-паттерном, как реальные *Module.php.
 */
final class FakeModule
{
}
