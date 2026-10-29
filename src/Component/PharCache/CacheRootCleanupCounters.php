<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

/**
 * Накопитель счётчиков одного прохода очистки кеш-корней PHAR.
 *
 * Внутренняя деталь {@see CacheRootGarbageCollector}: мутируемый аккумулятор,
 * преобразуемый в иммутабельный {@see CacheRootCleanupReport}. Не является
 * публичным контрактом — используется только внутри компонента.
 *
 * @internal
 */
final class CacheRootCleanupCounters
{
    /** Узлов рассмотрено диспетчером (без `.`/`..`; текущий корень учитывается как рассмотренный). */
    public int $examinedEntries = 0;

    /** Устаревших кеш-корней удалено. */
    public int $removedRoots = 0;

    /** Симлинк-узлов удалено (unlink самого узла, без перехода к цели). */
    public int $removedSymlinks = 0;

    /** Активных корней сохранено (занятый flock чужого процесса). */
    public int $activePreserved = 0;

    /** Неизвестных узлов сохранено (без валидного маркера: legacy, незавершённые, файлы). */
    public int $unknownPreserved = 0;

    /** Чужих каталогов сохранено (владелец не совпал с posix_getuid). */
    public int $foreignPreserved = 0;

    /** Сбоев удаления (не прерывают перебор; warning-семантику обеспечивает Kernel). */
    public int $failures = 0;

    /**
     * Формирует иммутабельный отчёт по накопленным счётчикам (очистка выполнялась).
     */
    public function toReport(): CacheRootCleanupReport
    {
        return new CacheRootCleanupReport(
            performed: true,
            skipReason: null,
            examinedEntries: $this->examinedEntries,
            removedRoots: $this->removedRoots,
            removedSymlinks: $this->removedSymlinks,
            activePreserved: $this->activePreserved,
            unknownPreserved: $this->unknownPreserved,
            foreignPreserved: $this->foreignPreserved,
            failures: $this->failures,
        );
    }
}
