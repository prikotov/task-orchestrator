<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;

/**
 * Контракт записи в сессию оркестрации.
 *
 * Операции, изменяющие состояние сессии: создание, логирование раундов,
 * запись файлов, завершение и прерывание.
 */
interface ChainSessionWriterInterface
{
    /**
     * Создаёт новую сессию и возвращает путь к директории.
     *
     * @param list<string> $participants
     */
    public function startSession(
        string $chainName,
        string $topic,
        string $facilitator,
        array $participants,
        int $maxRounds,
    ): string;

    /**
     * Логирует завершённый раунд.
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
    ): void;

    /**
     * Завершает сессию — записывает итог.
     */
    public function completeSession(
        ?string $synthesis,
        float $totalTime,
        int $totalInputTokens,
        int $totalOutputTokens,
        float $totalCost,
        int $totalSteps,
        string $reason = 'facilitator_done',
    ): void;

    /**
     * Устанавливает бюджетные ограничения цепочки для записи в session.json.
     */
    public function setBudget(?BudgetVo $budget): void;

    /**
     * Отмечает сессию как прерванную.
     */
    public function interruptSession(string $reason = ''): void;

    /**
     * Обновляет session.json с текущим состоянием (для resume).
     */
    public function updateSessionState(int $completedRounds): void;

    /**
     * Сохраняет параметры запуска команды в session.json.
     *
     * Длинные текстовые значения (topic, task) маскируются
     * для читаемости. Массив уже замаскирован на стороне вызывающего кода.
     *
     * @param array<string, mixed> $invocation ассоциативный массив с параметрами CLI
     */
    public function logInvocation(array $invocation): void;

    /**
     * Записывает файл контекста в сессию и возвращает абсолютный путь.
     *
     * Используется для передачи фасилитатору/участникам ссылок на файлы
     * вместо inline-текста (экономия токенов).
     */
    public function writeContextFile(string $name, string $content): string;

    /**
     * Записывает файл промпта в папку сессии с указанным суффиксом.
     *
     * Файл записывается ДО запуска раннера, чтобы путь к нему
     * можно было подставить в command вместо @-слота.
     *
     * @param string $suffix суффикс файла: '_1_system.md' или '_1b_append.md'
     *
     * @return string абсолютный путь к файлу (например .../step_002_round_001_system_architect_1_system.md)
     */
    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string;
}
