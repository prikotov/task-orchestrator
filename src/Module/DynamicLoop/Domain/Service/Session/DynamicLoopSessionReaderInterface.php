<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;

/**
 * Контракт чтения состояния сессии dynamic-цикла.
 */
interface DynamicLoopSessionReaderInterface
{
    public function getResumedState(): ?DynamicLoopSessionStateVo;

    /**
     * @return list<array{role: string, path: string}>
     */
    public function getResponseFilePaths(int $upToStep): array;

    /**
     * @return array<int, array{is_facilitator: bool, ...}>
     */
    public function getRoundFiles(): array;
}
