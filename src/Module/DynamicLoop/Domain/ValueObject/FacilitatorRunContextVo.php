<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Контекст одного запуска фасилитатора в dynamic-цикле.
 *
 * Инкапсулирует хвост сигнатуры RunDynamicLoopAgentServiceInterface::runFacilitator(),
 * оставляя в методе только позиционные параметры идентификации раунда
 * (step, round, facilitatorRole, topic).
 *
 * Группы полей (жизненный цикл разный, не смешивать при расширении):
 * - промпты раунда: brainstormSystemPrompt, facilitatorAppendPrompt,
 *   facilitatorStartPrompt, facilitatorContinuePrompt;
 * - состояние раунда: facilitatorSummary, responseFilesList, taskAddendum;
 * - runtime-опции запуска: workingDir, timeout, command, retryPolicy.
 */
final readonly class FacilitatorRunContextVo
{
    /**
     * @param string|null $workingDir рабочая директория агента (null — по умолчанию runner'а)
     * @param string|null $taskAddendum дополнение от Лида при resume (аргумент task
     *        CLI-команды); рендерится блоком в continue/start-промпте.
     *        null — дополнения нет, блок не рендерится.
     * @param list<string> $command полная CLI-команда из role config (пустой список — дефолт runner'а)
     * @param DynamicLoopRetryPolicyVo|null $retryPolicy политика transient-retry для шага
     *        (пробрасывается в AgentRunner). null — retry выключен.
     *
     * @throws InvalidArgumentException если timeout не положительный
     */
    public function __construct(
        private string $brainstormSystemPrompt,
        private string $facilitatorAppendPrompt,
        private string $facilitatorStartPrompt,
        private string $facilitatorContinuePrompt,
        private ?string $workingDir,
        private string $facilitatorSummary,
        private string $responseFilesList,
        private int $timeout,
        private array $command = [],
        private ?DynamicLoopRetryPolicyVo $retryPolicy = null,
        private ?string $taskAddendum = null,
    ) {
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Facilitator run timeout must be positive.');
        }
    }

    public function getBrainstormSystemPrompt(): string
    {
        return $this->brainstormSystemPrompt;
    }

    public function getFacilitatorAppendPrompt(): string
    {
        return $this->facilitatorAppendPrompt;
    }

    public function getFacilitatorStartPrompt(): string
    {
        return $this->facilitatorStartPrompt;
    }

    public function getFacilitatorContinuePrompt(): string
    {
        return $this->facilitatorContinuePrompt;
    }

    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
    }

    public function getFacilitatorSummary(): string
    {
        return $this->facilitatorSummary;
    }

    public function getResponseFilesList(): string
    {
        return $this->responseFilesList;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getRetryPolicy(): ?DynamicLoopRetryPolicyVo
    {
        return $this->retryPolicy;
    }

    public function getTaskAddendum(): ?string
    {
        return $this->taskAddendum;
    }
}
