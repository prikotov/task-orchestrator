<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorFinalizeRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\ParticipantRunContextVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 */
interface RunDynamicLoopAgentServiceInterface
{
    /**
     * @param string $facilitatorRole роль фасилитатора
     * @param string $topic задача раунда (аргумент task CLI-команды)
     * @param FacilitatorRunContextVo $runContext контекст запуска: промпты раунда,
     *        состояние раунда (summary, список файлов ответов, taskAddendum)
     *        и runtime-опции (workingDir, timeout, command, retryPolicy)
     *
     * @return array{DynamicLoopTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitator(
        int $step,
        int $round,
        string $facilitatorRole,
        string $topic,
        FacilitatorRunContextVo $runContext,
    ): array;

    /**
     * @param string $role роль участника
     * @param string $topic задача раунда (аргумент task CLI-команды)
     * @param ParticipantRunContextVo $runContext контекст запуска: промпты раунда,
     *        состояние раунда (список файлов ответов, hasPreviousResponses, challenge)
     *        и runtime-опции (workingDir, timeout, command, promptFile, retryPolicy)
     */
    public function runParticipant(
        int $step,
        int $round,
        string $role,
        string $topic,
        ParticipantRunContextVo $runContext,
    ): DynamicLoopTurnResultVo;

    /**
     * @param string $facilitatorRole роль фасилитатора
     * @param string $topic задача раунда (аргумент task CLI-команды)
     * @param FacilitatorFinalizeRunContextVo $runContext контекст запуска: промпты раунда,
     *        состояние раунда (список файлов ответов) и runtime-опции
     *        (workingDir, timeout, command, retryPolicy)
     */
    public function runFacilitatorFinalize(
        int $step,
        int $round,
        string $facilitatorRole,
        string $topic,
        FacilitatorFinalizeRunContextVo $runContext,
    ): DynamicLoopTurnResultVo;
}
