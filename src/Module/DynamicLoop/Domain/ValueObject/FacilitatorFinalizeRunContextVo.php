<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Контекст финального запуска фасилитатора (finalize) в dynamic-цикле.
 *
 * Инкапсулирует хвост сигнатуры RunDynamicLoopAgentServiceInterface::runFacilitatorFinalize(),
 * оставляя в методе только позиционные параметры идентификации раунда
 * (step, round, facilitatorRole, topic).
 *
 * Группы полей (жизненный цикл разный, не смешивать при расширении):
 * - промпты раунда: brainstormSystemPrompt, facilitatorAppendPrompt,
 *   facilitatorFinalizePrompt;
 * - состояние раунда: responseFilesList;
 * - runtime-опции запуска: workingDir, timeout, command, retryPolicy.
 */
final readonly class FacilitatorFinalizeRunContextVo
{
    /**
     * @param string|null $workingDir рабочая директория агента (null — по умолчанию runner'а)
     * @param list<string> $command полная CLI-команда из role config (пустой список — дефолт runner'а)
     * @param DynamicLoopRetryPolicyVo|null $retryPolicy политика transient-retry для шага
     *        (пробрасывается в AgentRunner). null — retry выключен.
     *
     * @throws InvalidArgumentException если timeout не положительный
     */
    public function __construct(
        private string $brainstormSystemPrompt,
        private string $facilitatorAppendPrompt,
        private string $facilitatorFinalizePrompt,
        private ?string $workingDir,
        private string $responseFilesList,
        private int $timeout,
        private array $command = [],
        private ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ) {
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Facilitator finalize run timeout must be positive.');
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

    public function getFacilitatorFinalizePrompt(): string
    {
        return $this->facilitatorFinalizePrompt;
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

    public function getRetryPolicy(): ?DynamicLoopRetryPolicyVo
    {
        return $this->retryPolicy;
    }
}
