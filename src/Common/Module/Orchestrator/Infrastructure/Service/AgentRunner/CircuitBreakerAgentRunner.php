<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\AgentRunner;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\CircuitStateEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\CircuitBreakerStateVo;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Декоратор AgentRunnerInterface — реализует Circuit Breaker.
 *
 * Отслеживает ошибки внутреннего runner'а и при достижении порога
 * (failureThreshold) блокирует вызовы на resetTimeoutSeconds.
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

    public function __construct(
        private readonly AgentRunnerInterface $innerRunner,
        private readonly CircuitBreakerStateVo $defaultState,
        private readonly LoggerInterface $logger,
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
            $this->logger->warning(sprintf(
                '[CircuitBreaker] Runner "%s": HalfOpen → Open (probe call failed). %s',
                $runnerName,
                $newState->toLogString(),
            ));
        } elseif ($previousState === CircuitStateEnum::closed && $newEffective === CircuitStateEnum::open) {
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
            $this->logger->info(sprintf(
                '[CircuitBreaker] Runner "%s": HalfOpen → Closed (probe call succeeded).',
                $runnerName,
            ));
        }

        $this->states[$runnerName] = $state->recordSuccess();
    }
}
