<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Dto;

/**
 * DTO-фикстура в исключённом пути Domain/Dto/: не регистрируется (excluded),
 * хотя сам класс instantiable — проверяет механизм exclude-путей.
 */
final class ExcludedDto
{
}
