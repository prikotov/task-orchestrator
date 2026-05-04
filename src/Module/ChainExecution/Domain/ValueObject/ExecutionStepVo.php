<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;
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

    public function getNoContextFiles(): bool
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
     * Преобразует quality_gate-шаг в ExecutionQualityGateVo.
     *
     * @throws \LogicException если шаг не является quality_gate
     */
    public function toQualityGateVo(): ExecutionQualityGateVo
    {
        if (!$this->isQualityGate()) {
            throw new \LogicException('Only quality_gate steps can be converted to ExecutionQualityGateVo.');
        }

        return new ExecutionQualityGateVo(
            command: $this->command,
            label: $this->label,
            timeoutSeconds: $this->timeoutSeconds,
        );
    }
}
