<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;

/**
 * Фабрика шагов цепочки оркестрации ({@see ChainStepVo}).
 *
 * Авторитетная граница создания step-level VO: централизует guard-инварианты
 * (обязательные role/command/label), применение object-level дефолтов runner/timeout
 * через константы {@see ChainStepVo}, наследование настроек цепочки на шаг
 * (правило приоритета «шаг перекрывает цепочку») и перенос факта явного задания runner.
 *
 * Не выполняет I/O, не зависит от внешних слоёв и не знает YAML-DSL keys: фабрика
 * принимает типизированные примитивы и готовые VO. Техническое извлечение значений
 * из raw YAML выполняет {@see \TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper}.
 *
 * @see docs/conventions/core_patterns/factory.md
 */
final readonly class ChainStepFactory
{
    /**
     * Создаёт agent-шаг (вызов AI-агента в указанной роли).
     *
     * @param string $chainName имя цепочки — используется в сообщениях исключений (guard-инварианты)
     * @param string $role роль агента; обязательна, иначе выбрасывается исключение с backward-compatible сообщением
     * @param string|null $runner runner агента (null → дефолт {@see ChainStepVo::DEFAULT_RUNNER})
     * @param bool $runnerExplicit факт явного задания runner на уровне YAML (переносится без повторного вычисления)
     * @param string|null $tools инструменты агента
     * @param string|null $model модель для переопределения
     * @param ChainRetryPolicyVo|null $stepRetryPolicy retry-политика уровня шага (приоритет над цепочной)
     * @param ChainRetryPolicyVo|null $chainRetryPolicy retry-политика уровня цепочки (fallback)
     * @param string|null $name опциональное имя шага для ссылок из fix_iterations
     * @param bool|null $stepNoContextFiles флаг no_context_files уровня шага (null = отсутствует → наследуется цепочка)
     * @param bool $chainNoContextFiles флаг no_context_files уровня цепочки (fallback)
     * @param ConditionExpressionVo|null $when условное выражение выполнения шага
     * @param string|null $postStep путь к post_step hook-скрипту
     *
     * @throws InvalidArgumentException если role отсутствует
     */
    public function createAgent(
        string $chainName,
        string $role,
        ?string $runner,
        bool $runnerExplicit,
        ?string $tools = null,
        ?string $model = null,
        ?ChainRetryPolicyVo $stepRetryPolicy = null,
        ?ChainRetryPolicyVo $chainRetryPolicy = null,
        ?string $name = null,
        ?bool $stepNoContextFiles = null,
        bool $chainNoContextFiles = false,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
    ): ChainStepVo {
        if ($role === '') {
            throw new InvalidArgumentException(
                sprintf('Agent step "role" is required in chain "%s".', $chainName),
            );
        }

        return ChainStepVo::createAgent(
            role: $role,
            runner: $runner ?? ChainStepVo::DEFAULT_RUNNER,
            tools: $tools,
            model: $model,
            retryPolicy: $stepRetryPolicy ?? $chainRetryPolicy,
            name: $name,
            noContextFiles: $stepNoContextFiles ?? $chainNoContextFiles,
            when: $when,
            postStep: $postStep,
            runnerExplicit: $runnerExplicit,
        );
    }

    /**
     * Создаёт tool-шаг (детерминированная shell-команда, stdout → ChainContext).
     *
     * @param string $chainName имя цепочки — используется в сообщениях исключений (guard-инварианты)
     * @param string|null $command shell-команда; обязательна, иначе исключение с backward-compatible сообщением
     * @param string|null $label человекочитаемое название; обязательно, иначе исключение
     * @param int|null $timeoutSeconds таймаут выполнения (null → дефолт {@see ChainStepVo::DEFAULT_TIMEOUT_SECONDS})
     * @param string|null $outputKey ключ для записи stdout в ChainContext (только для tool)
     * @param string|null $name опциональное имя шага
     * @param ConditionExpressionVo|null $when условное выражение выполнения шага
     * @param string|null $postStep путь к post_step hook-скрипту
     *
     * @throws InvalidArgumentException если отсутствуют command или label
     */
    public function createTool(
        string $chainName,
        ?string $command,
        ?string $label,
        ?int $timeoutSeconds = null,
        ?string $outputKey = null,
        ?string $name = null,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
    ): ChainStepVo {
        if ($command === null || $command === '') {
            throw new InvalidArgumentException(
                sprintf('Tool step must have "command" in chain "%s".', $chainName),
            );
        }

        if ($label === null || $label === '') {
            throw new InvalidArgumentException(
                sprintf('Tool step must have "label" in chain "%s".', $chainName),
            );
        }

        return ChainStepVo::createTool(
            command: $command,
            label: $label,
            timeoutSeconds: $timeoutSeconds ?? ChainStepVo::DEFAULT_TIMEOUT_SECONDS,
            outputKey: $outputKey,
            name: $name,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * Создаёт quality_gate-шаг (детерминированная проверка pass/fail).
     *
     * @param string $chainName имя цепочки — используется в сообщениях исключений (guard-инварианты)
     * @param string|null $command shell-команда; обязательна, иначе исключение с backward-compatible сообщением
     * @param string|null $label человекочитаемое название; обязательно, иначе исключение
     * @param int|null $timeoutSeconds таймаут выполнения (null → дефолт {@see ChainStepVo::DEFAULT_TIMEOUT_SECONDS})
     * @param string|null $name опциональное имя шага
     * @param ConditionExpressionVo|null $when условное выражение выполнения шага
     * @param string|null $postStep путь к post_step hook-скрипту
     *
     * @throws InvalidArgumentException если отсутствуют command или label
     */
    public function createQualityGate(
        string $chainName,
        ?string $command,
        ?string $label,
        ?int $timeoutSeconds = null,
        ?string $name = null,
        ?ConditionExpressionVo $when = null,
        ?string $postStep = null,
    ): ChainStepVo {
        if ($command === null || $command === '') {
            throw new InvalidArgumentException(
                sprintf('quality_gate step must have "command" in chain "%s".', $chainName),
            );
        }

        if ($label === null || $label === '') {
            throw new InvalidArgumentException(
                sprintf('quality_gate step must have "label" in chain "%s".', $chainName),
            );
        }

        return ChainStepVo::createQualityGate(
            command: $command,
            label: $label,
            timeoutSeconds: $timeoutSeconds ?? ChainStepVo::DEFAULT_TIMEOUT_SECONDS,
            name: $name,
            when: $when,
            postStep: $postStep,
        );
    }
}
