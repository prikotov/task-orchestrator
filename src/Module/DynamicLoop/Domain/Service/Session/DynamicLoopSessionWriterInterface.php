<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;

/**
 * Контракт записи в сессию dynamic-цикла.
 *
 * Перенесён из ChainDefinition.Domain\Service\Chain\Session\ChainSessionWriterInterface.
 * BudgetVo заменён на DynamicLoopBudgetVo.
 */
interface DynamicLoopSessionWriterInterface
{
    /**
     * Начинает сессию и сохраняет сложность задачи в session.json:
     * при resume без явной CLI-опции --complexity используется сохранённое значение.
     *
     * @param list<string> $participants
     * @param int $complexity уровень сложности задачи 0–5 (C0–C5), выбранный на CLI-границе
     */
    public function startSession(
        string $chainName,
        string $topic,
        string $facilitator,
        array $participants,
        int $maxRounds,
        int $complexity,
    ): string;

    /**
     * Записывает один шаг сессии (round) в session.json.
     *
     * Модельные поля и кэш-токены заполняются, если раннер их сообщил;
     * null/0 — записываются как отсутствие данных (старые сессии читаются без них).
     */
    public function logRound(
        int $step,
        int $round,
        string $role,
        bool $isFacilitator,
        string $systemPrompt,
        string $userPrompt,
        string $response,
        float $duration,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        ?string $invocation = null,
        bool $isError = false,
        ?string $errorMessage = null,
        ?string $model = null,
        ?string $provider = null,
        ?string $thinking = null,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
    ): void;

    public function completeSession(
        ?string $synthesis,
        float $totalTime,
        int $totalInputTokens,
        int $totalOutputTokens,
        float $totalCost,
        int $totalSteps,
        string $reason = 'facilitator_done',
    ): void;

    public function setBudget(?DynamicLoopBudgetVo $budget): void;

    public function interruptSession(string $reason = ''): void;

    public function updateSessionState(int $completedRounds): void;

    /**
     * Запоминает invocation текущего запуска и перезаписывает session.json.
     *
     * Семантика latest-wins: верхнеуровневое invocation в session.json всегда
     * соответствует последнему вызову; при resume значение исходного запуска
     * заменяется значением текущего.
     *
     * @param array<string, mixed> $invocation
     */
    public function logInvocation(array $invocation): void;

    public function writeContextFile(string $name, string $content): string;

    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string;
}
