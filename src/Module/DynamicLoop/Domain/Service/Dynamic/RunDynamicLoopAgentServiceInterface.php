<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 */
interface RunDynamicLoopAgentServiceInterface
{
    /**
     * @param list<string> $command
     *
     * @return array{DynamicLoopTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitator(
        int $step,
        int $round,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
        ?string $workingDir,
        string $facilitatorSummary,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ): array;

    /**
     * @param list<string> $command
     */
    public function runParticipant(
        int $step,
        int $round,
        string $role,
        string $topic,
        string $brainstormSystemPrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        bool $hasPreviousResponses = true,
        ?string $challenge = null,
        ?string $promptFile = null,
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ): DynamicLoopTurnResultVo;

    /**
     * @param list<string> $command
     */
    public function runFacilitatorFinalize(
        int $step,
        int $round,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorFinalizePrompt,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ): DynamicLoopTurnResultVo;
}
