<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Имена записей каталога procfs без специальных записей `.` и `..`.
 */
final readonly class ProcFilesystemDirectoryEntriesDto
{
    public function __construct(
        /** @var list<string> */
        public array $entries,
    ) {
    }
}
