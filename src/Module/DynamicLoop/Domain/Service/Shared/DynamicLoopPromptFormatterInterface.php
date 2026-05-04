<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Shared;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo;

/**
 * Форматирует промпты и собирает артефакты запуска агентов в dynamic-цикле.
 */
interface DynamicLoopPromptFormatterInterface
{
    public function buildFacilitatorContext(
        string $startPrompt,
        string $continuePrompt,
        string $topic,
        string $facilitatorSummary,
        string $responseFilesList,
    ): string;

    public function buildFinalizeContext(
        string $finalizePrompt,
        string $topic,
        string $responseFilesList,
    ): string;

    public function buildParticipantUserPrompt(
        string $userPromptTemplate,
        string $topic,
        string $responseFilesList,
        bool $hasPreviousResponses,
        ?string $challenge,
    ): string;

    /**
     * @param array<string> $command
     * @return list<string>
     */
    public function resolveSlot(
        array $command,
        string $marker,
        string $sessionFilePath,
        string $fallbackKey,
    ): array;

    public function buildAgentInvocation(
        DynamicLoopRunRequestVo $request,
        string $userPromptFile,
    ): string;

    public function buildUserPromptFileName(
        int $step,
        int $round,
        string $role,
    ): string;
}
