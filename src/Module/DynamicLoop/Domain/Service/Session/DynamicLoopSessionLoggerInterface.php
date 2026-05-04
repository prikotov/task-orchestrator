<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session;

/**
 * Агрегатный контракт логгера сессии dynamic-цикла.
 */
interface DynamicLoopSessionLoggerInterface extends
    DynamicLoopSessionWriterInterface,
    DynamicLoopSessionReaderInterface
{
    public function resumeSession(string $sessionDir): void;
}
