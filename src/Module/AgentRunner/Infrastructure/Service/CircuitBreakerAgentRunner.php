<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum\CircuitStateEnum;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\CircuitBreakerStateVo;
use Throwable;

/**
 * Декоратор AgentRunnerInterface — реализует Circuit Breaker с опциональным fallback.
 *
 * Отслеживает ошибки внутреннего runner'а и при достижении порога
 * (failureThreshold) блокирует вызовы на resetTimeoutSeconds.
 *
 * При CB open: если сконфигурирован fallback runner — делегирует вызов на него
 * (с подстановкой fallback command). Если fallback не задан — возвращает ошибку.
 *
 * Состояния: Closed → Open → HalfOpen → Closed.
 * State хранится in-memory (array), ключ — имя runner'а.
 *
 * Не изменяет AgentRunnerInterface — чистый Decorator pattern.
 */
final class CircuitBreakerAgentRunner implements AgentRunnerInterface
{
    /** @var array<string, CircuitBreakerStateVo> in-memory хранилище состояний */
    private array $states = [];

    /**
     * @param list<string> $fallbackCommand CLI-команда fallback runner'а.
     *        Если пуста — fallback runner получит request без изменения command.
     */
    public function __construct(
        private readonly AgentRunnerInterface $innerRunner,
        private readonly CircuitBreakerStateVo $defaultState,
        private readonly LoggerInterface $logger,
        private readonly ?AgentRunnerInterface $fallbackRunner = null,
        private readonly array $fallbackCommand = [],
        private readonly ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return $this->innerRunner->getName();
    }

    #[Override]
    public function isAvailable(): bool
    {
        return $this->innerRunner->isAvailable();
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        $runnerName = $this->innerRunner->getName();
        $state = $this->getState($runnerName);

        // Проверяем эффективное состояние с учётом автоматического перехода Open → HalfOpen
        $effectiveState = $state->getEffectiveState();

        if ($effectiveState === CircuitStateEnum::open) {
            $this->metrics?->recordCounter('cb.rejection', 1, [
                'runner' => $runnerName,
            ]);

            // Если fallback runner сконфигурирован — делегируем на него
            if ($this->fallbackRunner !== null) {
                return $this->runFallback($request, $runnerName, $state);
            }

            $this->logger->warning(sprintf(
                '[CircuitBreaker] Runner "%s" is OPEN — call blocked. %s',
                $runnerName,
                $state->toLogString(),
            ));

            return AgentResultVo::createFromError(
                errorMessage: sprintf(
                    'Circuit breaker is open for runner "%s". %s',
                    $runnerName,
                    $state->toLogString(),
                ),
            );
        }

        // Closed или HalfOpen — пропускаем вызов
        try {
            $result = $this->innerRunner->run($request);

            if ($result->isError()) {
                $this->handleFailure($runnerName, $state);

                return $result;
            }

            $this->handleSuccess($runnerName, $state);

            return $result;
        } catch (Throwable $throwable) {
            $this->handleFailure($runnerName, $state);

            throw $throwable;
        }
    }

    /**
     * Делегирует вызов на fallback runner при CB open.
     *
     * Подставляет fallback command в request (если задана).
     * Логирует факт fallback-вызова и его результат.
     */
    private function runFallback(AgentRunRequestVo $request, string $runnerName, CircuitBreakerStateVo $state): AgentResultVo
    {
        $fallbackRunner = $this->fallbackRunner;
        assert($fallbackRunner !== null);
        $fallbackName = $fallbackRunner->getName();

        $this->logger->warning(sprintf(
            '[CircuitBreaker] Runner "%s" is OPEN — delegating to fallback runner "%s". %s',
            $runnerName,
            $fallbackName,
            $state->toLogString(),
        ));

        $fallbackRequest = $this->buildFallbackRequest($request);

        try {
            $result = $fallbackRunner->run($fallbackRequest);

            if ($result->isError()) {
                $this->logger->error(sprintf(
                    '[CircuitBreaker] Fallback runner "%s" also failed for role "%s": %s',
                    $fallbackName,
                    $request->getRole(),
                    $result->getErrorMessage() ?? 'unknown',
                ));

                return $result;
            }

            $this->logger->info(sprintf(
                '[CircuitBreaker] Fallback runner "%s" succeeded for role "%s" (primary "%s" was OPEN).',
                $fallbackName,
                $request->getRole(),
                $runnerName,
            ));

            return $result;
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf(
                '[CircuitBreaker] Fallback runner "%s" threw exception for role "%s": %s',
                $fallbackName,
                $request->getRole(),
                $throwable->getMessage(),
            ));

            return AgentResultVo::createFromError(
                errorMessage: sprintf(
                    'Circuit breaker is open for runner "%s" and fallback runner "%s" threw exception: %s',
                    $runnerName,
                    $fallbackName,
                    $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * Создаёт AgentRunRequestVo с fallback command вместо оригинальной.
     *
     * Если fallback command не задана — возвращает оригинальный request без изменений.
     */
    private function buildFallbackRequest(AgentRunRequestVo $request): AgentRunRequestVo
    {
        if ($this->fallbackCommand === []) {
            return $request;
        }

        return new AgentRunRequestVo(
            role: $request->getRole(),
            task: $request->getTask(),
            systemPrompt: $request->getSystemPrompt(),
            previousContext: $request->getPreviousContext(),
            model: $request->getModel(),
            tools: $request->getTools(),
            workingDir: $request->getWorkingDir(),
            timeout: $request->getTimeout(),
            maxContextLength: $request->getMaxContextLength(),
            command: $this->fallbackCommand,
            runnerArgs: $request->getRunnerArgs(),
            noContextFiles: $request->getNoContextFiles(),
        );
    }

    /**
     * Возвращает текущее состояние circuit breaker для runner'а.
     */
    public function getCircuitState(string $runnerName): CircuitBreakerStateVo
    {
        return $this->getState($runnerName);
    }

    private function getState(string $runnerName): CircuitBreakerStateVo
    {
        return $this->states[$runnerName] ?? $this->defaultState;
    }

    private function handleFailure(string $runnerName, CircuitBreakerStateVo $state): void
    {
        $previousState = $state->getEffectiveState();
        $newState = $state->recordFailure();
        $this->states[$runnerName] = $newState;

        $newEffective = $newState->getEffectiveState();

        if ($previousState === CircuitStateEnum::halfOpen && $newEffective === CircuitStateEnum::open) {
            $this->metrics?->recordCounter('cb.state_change', 1, [
                'runner' => $runnerName,
                'from' => 'half_open',
                'to' => 'open',
            ]);

            $this->logger->warning(sprintf(
                '[CircuitBreaker] Runner "%s": HalfOpen → Open (probe call failed). %s',
                $runnerName,
                $newState->toLogString(),
            ));
        } elseif ($previousState === CircuitStateEnum::closed && $newEffective === CircuitStateEnum::open) {
            $this->metrics?->recordCounter('cb.state_change', 1, [
                'runner' => $runnerName,
                'from' => 'closed',
                'to' => 'open',
            ]);

            $this->logger->warning(sprintf(
                '[CircuitBreaker] Runner "%s": Closed → Open (failure threshold reached). %s',
                $runnerName,
                $newState->toLogString(),
            ));
        }
    }

    private function handleSuccess(string $runnerName, CircuitBreakerStateVo $state): void
    {
        $previousState = $state->getEffectiveState();

        if ($previousState === CircuitStateEnum::halfOpen) {
            $this->metrics?->recordCounter('cb.state_change', 1, [
                'runner' => $runnerName,
                'from' => 'half_open',
                'to' => 'closed',
            ]);

            $this->logger->info(sprintf(
                '[CircuitBreaker] Runner "%s": HalfOpen → Closed (probe call succeeded).',
                $runnerName,
            ));
        }

        $this->states[$runnerName] = $state->recordSuccess();
    }
}
