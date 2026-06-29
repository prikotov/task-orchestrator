<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service;

/**
 * Enum-фикстура: не регистрируется как сервис (isEnum). Расположен вне
 * исключённых путей, чтобы проверить ветку isInstantiable, а не exclude.
 */
enum StatusEnum: string
{
    case Active = 'active';
    case Stopped = 'stopped';
}
