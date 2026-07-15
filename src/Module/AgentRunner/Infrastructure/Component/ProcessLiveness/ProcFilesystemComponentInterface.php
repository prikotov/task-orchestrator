<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\{
    ProcFilesystemDirectoryEntriesDto,
};

/**
 * Низкоуровневое чтение файлов Linux procfs.
 */
interface ProcFilesystemComponentInterface
{
    /**
     * Возвращает содержимое файла либо null при ожидаемой недоступности чтения.
     */
    public function read(string $path): ?string;

    /**
     * Возвращает список имён без специальных записей каталога либо null
     * при ожидаемой недоступности.
     */
    public function listDirectory(string $path): ?ProcFilesystemDirectoryEntriesDto;
}
