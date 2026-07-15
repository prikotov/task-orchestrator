<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Component\AgentRunner;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcFilesystemDirectoryEntriesDto,
    ProcFilesystemComponentInterface,
};

/**
 * Детерминированная procfs-карта для Unit-тестов parser/component.
 */
final class ProcFilesystemFake implements ProcFilesystemComponentInterface
{
    /** @var list<string> */
    public array $readPaths = [];

    /** @var list<string> */
    public array $listedPaths = [];

    public function __construct(
        /** @var array<string, string|null> */
        public array $files,
        /** @var array<string, list<string>|null> */
        public array $directories = [],
    ) {
    }

    #[Override]
    public function read(string $path): ?string
    {
        $this->readPaths[] = $path;

        return $this->files[$path] ?? null;
    }

    #[Override]
    public function listDirectory(string $path): ?ProcFilesystemDirectoryEntriesDto
    {
        $this->listedPaths[] = $path;

        $entries = $this->directories[$path] ?? null;

        return $entries === null ? null : new ProcFilesystemDirectoryEntriesDto($entries);
    }
}
