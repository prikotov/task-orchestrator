<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcFilesystemDirectoryEntriesDto;

/**
 * Чтение procfs с нормализацией ожидаемых filesystem warning в null.
 */
final readonly class ProcFilesystemComponent implements ProcFilesystemComponentInterface
{
    #[Override]
    public function read(string $path): ?string
    {
        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($path);
        } finally {
            restore_error_handler();
        }

        return is_string($contents) ? $contents : null;
    }

    #[Override]
    public function listDirectory(string $path): ?ProcFilesystemDirectoryEntriesDto
    {
        set_error_handler(static fn (): bool => true);

        try {
            $entries = scandir($path);
        } finally {
            restore_error_handler();
        }

        if (!is_array($entries)) {
            return null;
        }

        return new ProcFilesystemDirectoryEntriesDto(array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
        )));
    }
}
