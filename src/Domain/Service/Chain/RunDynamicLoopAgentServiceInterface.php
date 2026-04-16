<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\ValueObject\AgentTurnResultVo;
use TasK\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 */
interface RunDynamicLoopAgentServiceInterface
{
    /**
     * Запускает фасилитатора и парсит его ответ.
     *
     * @param array<string> $command
     *
     * @return array{AgentTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitator(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
        ?string $model,
        ?string $workingDir,
        string $facilitatorSummary,
        string $responseFilesList,
        int $timeout,
        array $command = [],
    ): array;

    /**
     * Запускает участника динамической цепочки.
     *
     * @param array<string> $command
     */
    public function runParticipant(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $role,
        string $topic,
        string $brainstormSystemPrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        ?string $model,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        bool $hasPreviousResponses = true,
        ?string $challenge = null,
        ?string $promptFile = null,
    ): AgentTurnResultVo;

    /**
     * Запускает фасилитатора для финализации (без JSON-парсинга).
     *
     * @param array<string> $command
     */
    public function runFacilitatorFinalize(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorFinalizePrompt,
        ?string $model,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
    ): AgentTurnResultVo;
}
