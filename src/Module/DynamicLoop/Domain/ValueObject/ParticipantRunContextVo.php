<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Контекст одного запуска участника в dynamic-цикле.
 *
 * Инкапсулирует хвост сигнатуры RunDynamicLoopAgentServiceInterface::runParticipant(),
 * оставляя в методе только позиционные параметры идентификации раунда
 * (step, round, role, topic).
 *
 * Группы полей (жизненный цикл разный, не смешивать при расширении):
 * - промпты раунда: brainstormSystemPrompt, participantAppendPrompt,
 *   participantUserPrompt;
 * - состояние раунда: responseFilesList, hasPreviousResponses, challenge;
 * - runtime-опции запуска: workingDir, timeout, command, promptFile, retryPolicy.
 */
final readonly class ParticipantRunContextVo
{
    /**
     * @param string|null $workingDir рабочая директория агента (null — по умолчанию runner'а)
     * @param list<string> $command полная CLI-команда из role config (пустой список — дефолт runner'а)
     * @param bool $hasPreviousResponses есть ли ответы предыдущих шагов (влияет на рендер промпта)
     * @param string|null $challenge вопрос фасилитатора этому участнику (null — обычный раунд)
     * @param string|null $promptFile переопределение пути к файлу описания роли
     *        (null — резолвится через GetPromptFilePathQuery)
     * @param DynamicLoopRetryPolicyVo|null $retryPolicy политика transient-retry для шага
     *        (пробрасывается в AgentRunner). null — retry выключен.
     *
     * @throws InvalidArgumentException если timeout не положительный
     */
    public function __construct(
        private string $brainstormSystemPrompt,
        private string $participantAppendPrompt,
        private string $participantUserPrompt,
        private ?string $workingDir,
        private string $responseFilesList,
        private int $timeout,
        private array $command = [],
        private bool $hasPreviousResponses = true,
        private ?string $challenge = null,
        private ?string $promptFile = null,
        private ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ) {
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Participant run timeout must be positive.');
        }
    }

    public function getBrainstormSystemPrompt(): string
    {
        return $this->brainstormSystemPrompt;
    }

    public function getParticipantAppendPrompt(): string
    {
        return $this->participantAppendPrompt;
    }

    public function getParticipantUserPrompt(): string
    {
        return $this->participantUserPrompt;
    }

    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
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

    public function hasPreviousResponses(): bool
    {
        return $this->hasPreviousResponses;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    public function getPromptFile(): ?string
    {
        return $this->promptFile;
    }

    public function getRetryPolicy(): ?DynamicLoopRetryPolicyVo
    {
        return $this->retryPolicy;
    }
}
