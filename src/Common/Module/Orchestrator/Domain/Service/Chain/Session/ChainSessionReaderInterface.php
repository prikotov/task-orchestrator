<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo;

/**
 * Контракт чтения состояния сессии оркестрации.
 *
 * Операции, не изменяющие состояние: получение данных для resume,
 * путей к response-файлам и информации о раундах.
 */
interface ChainSessionReaderInterface
{
    /**
     * Возвращает состояние восстановленной сессии или null.
     */
    public function getResumedState(): ?ChainSessionStateVo;

    /**
     * Возвращает относительные пути к response-файлам participant-раундов до шага $upToStep.
     *
     * @return list<string>
     */
    public function getResponseFilePaths(int $upToStep): array;

    /**
     * Возвращает массив roundFiles для подсчёта participant-раундов.
     *
     * @return array<int, array{is_facilitator: bool, ...}>
     */
    public function getRoundFiles(): array;
}
