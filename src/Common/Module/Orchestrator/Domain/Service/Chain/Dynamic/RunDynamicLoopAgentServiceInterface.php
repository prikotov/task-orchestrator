<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 */
interface RunDynamicLoopAgentServiceInterface
{
    /**
     * Запускает фасилитатора и парсит его ответ.
     *
     * @param list<string> $command
     *
     * @return array{ChainTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitator(
        int $step,
        int $round,
        RunAgentServiceInterface $runner,
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
     * @param list<string> $command
     */
    public function runParticipant(
        int $step,
        int $round,
        RunAgentServiceInterface $runner,
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
    ): ChainTurnResultVo;

    /**
     * Запускает фасилитатора для финализации (без JSON-парсинга).
     *
     * @param list<string> $command
     */
    public function runFacilitatorFinalize(
        int $step,
        int $round,
        RunAgentServiceInterface $runner,
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
    ): ChainTurnResultVo;
}
