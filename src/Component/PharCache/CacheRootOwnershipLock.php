<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

/**
 * Эксклюзивная блокировка владения кеш-корнем PHAR на файле-маркере.
 *
 * Дескриптор файла-маркера {@see CacheRootOwnerMarker::FILENAME} открывается и
 * блокируется `flock(LOCK_EX|LOCK_NB)`: неудачный захват трактуется как признак
 * активного чужого корня (параллельный процесс task-orchestrator удерживает
 * блокировку). Один файл обслуживает и блокировку, и маркер владения — между
 * созданием маркера и захватом блокировки нет второй точки гонки.
 *
 * Два режима открытия:
 *  - {@see acquireToOwn()} — режим создания (`fopen ... c`): штатный путь
 *    захвата собственного корня (`mkdir` → `flock` → маркер); отсутствующий
 *    маркер создаётся;
 *  - {@see acquireToInspect()} — режим только чтения (`fopen ... r`):
 *    используется диспетчером очистки; отсутствующий маркер НЕ материализуется
 *    в чужих или неизвестных корнях.
 *
 * Экземпляр удерживает дескриптор до явного {@see release()} либо до разрушения
 * объекта (`__destruct` — страховка). Владелец собственного корня (Kernel)
 * удерживает блокировку до завершения процесса: при аварийном завершении
 * дескриптор закрывается и блокировка освобождается ОС.
 *
 * Класс без Symfony-зависимостей (конвенция core-patterns/component.md):
 * только flock/ftruncate/fwrite над переданными путями.
 *
 * Оператор `@` на файловых операциях — низкоуровневый I/O системного temp:
 * он подавляет только дублирующее PHP-warning, отказ каждой операции
 * проверяется по возвращаемому значению и трактуется как безопасный отказ
 * захвата/чтения (идиома для файловых функций PHP, cf. bridge.php в phpmd.xml).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class CacheRootOwnershipLock
{
    /** @var resource|closed-resource|null дескриптор файла-маркера (closed-resource после release) */
    private $handle;

    /**
     * @param resource $handle открытый и заблокированный дескриптор маркера
     */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Захватывает корень во владение: открывает (при необходимости создаёт)
     * файл-маркер и берёт эксклюзивную неблокирующую блокировку.
     *
     * @param string $rootDir каталог кеш-корня (должен существовать)
     */
    public static function acquireToOwn(string $rootDir): ?self
    {
        return self::open($rootDir, 'c');
    }

    /**
     * Открывает существующий маркер чужого корня для инспекции (создание
     * запрещено): берёт эксклюзивную неблокирующую блокировку — занятый файл
     * означает активный корень.
     *
     * @param string $rootDir каталог кеш-корня (должен существовать)
     */
    public static function acquireToInspect(string $rootDir): ?self
    {
        return self::open($rootDir, 'r');
    }

    /**
     * Записывает (перезаписывает) маркер владения под удерживаемой блокировкой.
     *
     * @param string $rootName self-hash корня (обязан совпадать с именем каталога)
     */
    public function writeMarker(string $rootName): bool
    {
        if (!is_resource($this->handle)) {
            return false;
        }

        if (@ftruncate($this->handle, 0) === false || @fseek($this->handle, 0) !== 0) {
            return false;
        }

        return @fwrite($this->handle, CacheRootOwnerMarker::encode($rootName)) !== false;
    }

    /**
     * Освобождает блокировку и закрывает дескриптор. Идемпотентен.
     */
    public function release(): void
    {
        if (is_resource($this->handle)) {
            @flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }

        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * Открывает файл-маркер в заданном режиме и захватывает блокировку.
     *
     * @param 'c'|'r' $mode 'c' — создать при отсутствии (владение), 'r' — только существующий (инспекция)
     */
    private static function open(string $rootDir, string $mode): ?self
    {
        $handle = @fopen($rootDir . '/' . CacheRootOwnerMarker::FILENAME, $mode);

        if ($handle === false) {
            return null;
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            // Блокировка удержана другим процессом — признак активного корня.
            fclose($handle);

            return null;
        }

        return new self($handle);
    }
}
