<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

use JsonException;

/**
 * Маркер владения кеш-корнем PHAR (контракт FR-4).
 *
 * Каждый изолированный кеш-корень `<sys_tmp>/task-orchestrator/<hash>` содержит
 * файл-маркер {@see FILENAME}, удостоверяющий, что каталог материализован именно
 * task-orchestrator для конкретной пары (host-проект, физический путь PHAR).
 * Маркер хранит self-hash — хеш текущей пары, обязанный совпадать с именем
 * каталога. Расхождение маркера и имени трактуется как чужой или неизвестный
 * корень: такие узлы очисткой не удаляются.
 *
 * Корень без маркера считается незавершённым (процесс не дошёл до записи после
 * `mkdir`) или чужим и очисткой не удаляется — в том числе legacy-корни,
 * созданные до введения маркера: их судьба задокументирована (сохраняются до
 * ручной чистки или ротации системного временного каталога).
 *
 * Класс чистый (без файловых операций и Symfony-зависимостей): кодирование и
 * разбор содержимого изолированы для модульного тестирования; файловые
 * операции выполняют {@see CacheRootOwnershipLock} (запись под flock)
 * и {@see CacheRootGarbageCollector} (проверки узлов через lstat).
 *
 * Маркер-файл совмещён с lock-файлом: блокировка flock берётся на тот же
 * дескриптор, что устраняет вторую точку гонки между созданием маркера и
 * захватом блокировки.
 */
final class CacheRootOwnerMarker
{
    /** Имя файла-маркера владения внутри кеш-корня (он же lock-файл). */
    public const string FILENAME = '.task-orchestrator-owner.json';

    /** Версия схемы содержимого маркера. */
    private const int SCHEMA_VERSION = 1;

    /**
     * Кодирует содержимое маркера для указанного имени корня.
     *
     * @param string $rootName self-hash корня (обязан совпадать с именем каталога)
     */
    public static function encode(string $rootName): string
    {
        return json_encode(
            ['schema' => self::SCHEMA_VERSION, 'root' => $rootName],
            JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    /**
     * Разбирает содержимое маркера и возвращает имя корня.
     *
     * Возвращает null для нераспознанного содержимого (пустой файл, повреждённый
     * JSON, другая схема, отсутствующее или нестроковое имя): такой корень
     * трактуется как неизвестный и не удаляется очисткой.
     *
     * @param string $payload сырое содержимое файла-маркера
     */
    public static function decode(string $payload): ?string
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(trim($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA_VERSION) {
            return null;
        }

        $rootName = $decoded['root'] ?? null;

        return is_string($rootName) && $rootName !== '' ? $rootName : null;
    }
}
