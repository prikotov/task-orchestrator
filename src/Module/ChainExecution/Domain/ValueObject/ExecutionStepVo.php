<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use LogicException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;

/**
 * Execution VO: данные одного шага цепочки для выполнения.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\ChainStepVo через Integration-маппер.
 * Содержит только данные, необходимые execution-слою.
 */
final readonly class ExecutionStepVo
{
    public function __construct(
        private ChainStepTypeEnum $type,
        private ?string $role = null,
        private string $runner = 'pi',
        private ?string $tools = null,
        private ?string $model = null,
        private ?ExecutionRetryPolicyVo $retryPolicy = null,
        private ?string $name = null,
        private string $command = '',
        private string $label = '',
        private int $timeoutSeconds = 120,
        private bool $noContextFiles = false,
        private ?ConditionExpressionVo $when = null,
        private ?string $postStep = null,
        private ?string $outputKey = null,
    ) {
    }

    public function getType(): ChainStepTypeEnum
    {
        return $this->type;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getRunner(): string
    {
        return $this->runner;
    }

    public function getTools(): ?string
    {
        return $this->tools;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getRetryPolicy(): ?ExecutionRetryPolicyVo
    {
        return $this->retryPolicy;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function hasNoContextFiles(): bool
    {
        return $this->noContextFiles;
    }

    public function getWhen(): ?ConditionExpressionVo
    {
        return $this->when;
    }

    public function hasCondition(): bool
    {
        return $this->when !== null;
    }

    public function getPostStep(): ?string
    {
        return $this->postStep;
    }

    public function hasPostStep(): bool
    {
        return $this->postStep !== null;
    }

    public function isAgent(): bool
    {
        return $this->type === ChainStepTypeEnum::agent;
    }

    public function isQualityGate(): bool
    {
        return $this->type === ChainStepTypeEnum::qualityGate;
    }

    /**
     * Является ли шаг tool-шагом (детерминированной командой с выводом в context).
     */
    public function isTool(): bool
    {
        return $this->type === ChainStepTypeEnum::tool;
    }

    /**
     * Возвращает ключ для записи stdout в ChainContext (только для tool-шагов).
     */
    public function getOutputKey(): ?string
    {
        return $this->outputKey;
    }

    /**
     * Преобразует quality_gate-шаг в ExecutionQualityGateVo.
     *
     * @throws LogicException если шаг не является quality_gate
     */
    public function toQualityGateVo(): ExecutionQualityGateVo
    {
        if (!$this->isQualityGate()) {
            throw new LogicException('Only quality_gate steps can be converted to ExecutionQualityGateVo.');
        }

        return new ExecutionQualityGateVo(
            command: $this->command,
            label: $this->label,
            timeoutSeconds: $this->timeoutSeconds,
        );
    }

    /**
     * Преобразует tool-шаг в ExecutionToolStepVo.
     *
     * @throws LogicException если шаг не является tool
     */
    public function toToolStepVo(): ExecutionToolStepVo
    {
        if (!$this->isTool()) {
            throw new LogicException('Only tool steps can be converted to ExecutionToolStepVo.');
        }

        return new ExecutionToolStepVo(
            command: $this->command,
            label: $this->label,
            timeoutSeconds: $this->timeoutSeconds,
            outputKey: $this->outputKey,
        );
    }
}
