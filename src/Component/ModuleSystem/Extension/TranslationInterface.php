<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\Extension;

/**
 * Контракт модуля, предоставляющего переводы (translation resources).
 *
 * Модуль, реализующий этот интерфейс, объявляет базовый каталог переводов и
 * (опционально) дополнительные пути. Trait ModuleKernelTrait подключает эти
 * пути к framework.translator.paths через prepend-extension-config во время
 * сборки контейнера.
 *
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\ModuleKernelTrait
 */
interface TranslationInterface
{
    /**
     * Абсолютный путь к базовому каталогу переводов модуля.
     */
    public function getBaseTranslationsPath(): string;

    /**
     * Дополнительные каталоги переводов.
     *
     * @return list<string>
     */
    public function getAdditionalTranslationsPaths(): array;
}
