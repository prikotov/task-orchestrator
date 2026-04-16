<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\AgentRunRequestVo;

/**
 * Форматирует промпты и собирает артефакты запуска агентов в цепочке.
 *
 * Чистые функции: string/array → string/array.
 * Не зависит от Application-слоя.
 */
interface PromptFormatterInterface
{
    /**
     * Формирует контекст промпта шага static-цепочки от предыдущего агента.
     */
    public function buildStaticContext(
        string $role,
        string $previousOutput,
        string $task,
    ): string;

    /**
     * Формирует контекст промпта фасилитатора (start или continue).
     */
    public function buildFacilitatorContext(
        string $startPrompt,
        string $continuePrompt,
        string $topic,
        string $facilitatorSummary,
        string $responseFilesList,
    ): string;

    /**
     * Формирует контекст финализации фасилитатора.
     */
    public function buildFinalizeContext(
        string $finalizePrompt,
        string $topic,
        string $responseFilesList,
    ): string;

    /**
     * Формирует пользовательский промпт участника.
     */
    public function buildParticipantUserPrompt(
        string $userPromptTemplate,
        string $topic,
        string $responseFilesList,
        bool $hasPreviousResponses,
        ?string $challenge,
    ): string;

    /**
     * Подставляет путь к файлу промпта в команду вместо маркера или добавляет флаг.
     *
     * @param array<string> $command
     * @return list<string>
     */
    public function resolveSlot(
        array $command,
        string $marker,
        string $sessionFilePath,
        string $fallbackKey,
    ): array;

    /**
     * Формирует строку команды pi для записи в session.json.
     */
    public function buildAgentInvocation(
        AgentRunRequestVo $request,
        string $userPromptFile,
    ): string;

    /**
     * Формирует имя файла пользовательского промпта для invocation.
     */
    public function buildUserPromptFileName(
        int $step,
        int $round,
        string $role,
    ): string;
}
