<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

/**
 * Агрегатный контракт логгера сессии оркестрации.
 *
 * Объединяет операции чтения и записи для потребителей,
 * которым нужен полный доступ к сессии.
 *
 * Содержит метод resumeSession(), который не относится исключительно
 * к чтению или записи — это операция жизненного цикла сессии,
 * требующая и загрузки данных (чтение), и подготовки к последующим
 * операциям (запись).
 *
 * @see ChainSessionWriterInterface
 * @see ChainSessionReaderInterface
 */
interface ChainSessionLoggerInterface extends
    ChainSessionWriterInterface,
    ChainSessionReaderInterface
{
    /**
     * Восстанавливает сессию из директории для resume.
     *
     * Операция жизненного цикла: загружает состояние (чтение)
     * и подготавливает логгер к последующим операциям записи.
     */
    public function resumeSession(string $sessionDir): void;
}
