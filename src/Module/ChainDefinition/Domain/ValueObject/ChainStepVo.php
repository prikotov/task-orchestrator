<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;
use LogicException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;

/**
 * Value Object одного шага цепочки оркестрации.
 *
 * Поддерживает три типа шагов:
 * - agent: выполнение AI-агентом в определённой роли
 * - quality_gate: выполнение детерминированной shell-команды (pass/fail)
 * - tool: выполнение детерминированной shell-команды с передачей stdout в context
 */
final readonly class ChainStepVo
{
    /**
     * Дефолтный runner для agent-шага, когда runner не задан явно в YAML.
     */
    public const string DEFAULT_RUNNER = 'pi';

    /**
     * Дефолтный таймаут выполнения в секундах для quality_gate/tool-шагов.
     */
    public const int DEFAULT_TIMEOUT_SECONDS = 120;

    private readonly bool $runnerExplicit;

    /**
     * @param ChainStepTypeEnum $type тип шага (agent | quality_gate | tool)
     * @param string|null $role роль агента (обязательно для agent, null для quality_gate/tool)
     * @param string $runner имя runner'а (только для agent)
     * @param string|null $tools инструменты агента (только для agent)
     * @param string|null $model модель для переопределения (только для agent)
     * @param ChainRetryPolicyVo|null $retryPolicy политика retry для шага
     * @param string|null $name опциональное имя шага для ссылок из fix_iterations
     * @param string $command shell-команда (обязательно для quality_gate/tool, пустая строка для agent)
     * @param string $label человекочитаемое название (обязательно для quality_gate/tool, пустая строка для agent)
     * @param int $timeoutSeconds таймаут выполнения в секундах (default 120 для quality_gate/tool)
     * @param bool $noContextFiles отключить автоматическую загрузку контекстных файлов проекта (AGENTS.md, CLAUDE.md)
     * @param ConditionExpressionVo|null $when условное выражение выполнения шага (null = безусловное выполнение)
     * @param string|null $postStep путь к post_step hook-скрипту (null = hook не сконфигурирован)
     * @param string|null $outputKey ключ для записи stdout в ChainContext (только для tool)
     */
    // phpcs:ignore
    public function __construct(
        private ChainStepTypeEnum $type,
        private ?string $role = null,
        private string $runner = self::DEFAULT_RUNNER,
        private ?string $tools = null,
        private ?string $model = null,
        private ?ChainRetryPolicyVo $retryPolicy = null,
        private ?string $name = null,
        private string $command = '',
        private string $label = '',
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        private bool $noContextFiles = false,
        private ?ConditionExpressionVo $when = null,
        private ?string $postStep = null,
        private ?string $outputKey = null,
        ?bool $runnerExplicit = null,
    ) {
        $this->runnerExplicit = $runnerExplicit ?? $runner !== self::DEFAULT_RUNNER;

        if ($type === ChainStepTypeEnum::agent && ($role === null || $role === '')) {
            throw new InvalidArgumentException('Agent step must have a role.');
        }

        if ($type === ChainStepTypeEnum::qualityGate) {
            if (trim($command) === '') {
                throw new InvalidArgumentException('Quality gate step must have a command.');
            }

            if (trim($label) === '') {
                throw new InvalidArgumentException('Quality gate step must have a label.');
            }
        }

        if ($type === ChainStepTypeEnum::tool) {
            if (trim($command) === '') {
                throw new InvalidArgumentException('Tool step must have a command.');
            }

            if (trim($label) === '') {
                throw new InvalidArgumentException('Tool step must have a label.');
            }
        }
    }

    /**
     * Фабричный метод для создания agent-шага.
     */
    public static function createAgent(
        string $role,
        ?string $runner = null,
        ?string $tools = null,
        ?string $model = null,
        ?ChainRetryPolicyVo $retryPolicy = null,
        ?string $name = null,
        bool $noContextFiles = false,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
        ?bool $runnerExplicit = null,
    ): self {
        return new self(
            type: ChainStepTypeEnum::agent,
            role: $role,
            runner: $runner ?? self::DEFAULT_RUNNER,
            tools: $tools,
            model: $model,
            retryPolicy: $retryPolicy,
            name: $name,
            noContextFiles: $noContextFiles,
            when: $when,
            postStep: $postStep,
            runnerExplicit: $runnerExplicit ?? $runner !== null,
        );
    }

    /**
     * Фабричный метод для создания quality_gate-шага.
     */
    public static function createQualityGate(
        string $command,
        string $label,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        ?string $name = null,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
    ): self {
        return new self(
            type: ChainStepTypeEnum::qualityGate,
            command: $command,
            label: $label,
            timeoutSeconds: $timeoutSeconds,
            name: $name,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * Фабричный метод для создания tool-шага.
     *
     * Tool-шаг — детерминированная shell-команда, stdout которой
     * записывается в ChainContext по ключу outputKey.
     */
    public static function createTool(
        string $command,
        string $label,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        ?string $outputKey = null,
        ?string $name = null,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
    ): self {
        return new self(
            type: ChainStepTypeEnum::tool,
            command: $command,
            label: $label,
            timeoutSeconds: $timeoutSeconds,
            outputKey: $outputKey,
            name: $name,
            when: $when,
            postStep: $postStep,
        );
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

    /**
     * Был ли runner явно задан на уровне шага цепочки.
     */
    public function hasExplicitRunner(): bool
    {
        return $this->runnerExplicit;
    }

    public function getTools(): ?string
    {
        return $this->tools;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getRetryPolicy(): ?ChainRetryPolicyVo
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

    /**
     * Возвращает ключ для записи stdout в ChainContext (только для tool-шагов).
     */
    public function getOutputKey(): ?string
    {
        return $this->outputKey;
    }

    /**
     * Отключена ли автоматическая загрузка контекстных файлов проекта.
     */
    public function hasNoContextFiles(): bool
    {
        return $this->noContextFiles;
    }

    /**
     * Возвращает условное выражение выполнения шага (null = безусловное выполнение).
     */
    public function getWhen(): ?ConditionExpressionVo
    {
        return $this->when;
    }

    /**
     * Имеет ли шаг условие выполнения.
     */
    public function hasCondition(): bool
    {
        return $this->when !== null;
    }

    /**
     * Возвращает путь к post_step hook-скрипту (null = hook не сконфигурирован).
     */
    public function getPostStep(): ?string
    {
        return $this->postStep;
    }

    /**
     * Имеет ли шаг сконфигурированный post_step hook.
     */
    public function hasPostStep(): bool
    {
        return $this->postStep !== null;
    }

    /**
     * Является ли шаг выполнением AI-агента.
     */
    public function isAgent(): bool
    {
        return $this->type === ChainStepTypeEnum::agent;
    }

    /**
     * Является ли шаг quality gate (детерминированной проверкой).
     */
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
     * Преобразует quality_gate-шаг в QualityGateVo для Runner.
     *
     * @throws \LogicException если шаг не является quality_gate
     */
    public function toQualityGateVo(): QualityGateVo
    {
        if (!$this->isQualityGate()) {
            throw new LogicException('Only quality_gate steps can be converted to QualityGateVo.');
        }

        return new QualityGateVo(
            command: $this->command,
            label: $this->label,
            timeoutSeconds: $this->timeoutSeconds,
        );
    }

    /**
     * Преобразует tool-шаг в ToolStepVo для Runner.
     *
     * @throws \LogicException если шаг не является tool
     */
    public function toToolStepVo(): ToolStepVo
    {
        if (!$this->isTool()) {
            throw new LogicException('Only tool steps can be converted to ToolStepVo.');
        }

        return new ToolStepVo(
            command: $this->command,
            label: $this->label,
            timeoutSeconds: $this->timeoutSeconds,
            outputKey: $this->outputKey,
        );
    }
}
