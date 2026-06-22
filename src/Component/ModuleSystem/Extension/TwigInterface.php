<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\Extension;

/**
 * Контракт модуля, предоставляющего Twig-шаблоны.
 *
 * Модуль, реализующий этот интерфейс, объявляет базовый каталог шаблонов с
 * собственным Twig-namespace и (опционально) дополнительные пути. Trait
 * ModuleKernelTrait регистрирует TwigCompilerPass для каждого такого модуля,
 * который добавляет пути в twig.loader.native_filesystem.
 *
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\ModuleKernelTrait
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\TwigCompilerPass
 */
interface TwigInterface
{
    /**
     * Абсолютный путь к базовому каталогу шаблонов модуля.
     */
    public function getBaseTemplatesPath(): string;

    /**
     * Twig-namespace для базового каталога шаблонов (например, «module.git_identity»).
     */
    public function getBaseTwigNamespace(): string;

    /**
     * Дополнительные пары путь => namespace.
     *
     * @return array<string, string>
     */
    public function getAdditionalTemplatesPaths(): array;
}
