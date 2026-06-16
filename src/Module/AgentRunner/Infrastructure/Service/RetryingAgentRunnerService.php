<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryingAgentRunnerServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\ErrorClassificationVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use Throwable;

/**
 * Декоратор AgentRunnerInterface — добавляет retry с exponential backoff.
 *
 * Оборачивает любой AgentRunnerInterface и при выбросе исключения
 * повторяет вызов с задержкой по exponential backoff.
 * После исчерпания всех попыток возвращает AgentResultVo::createError().
 *
 * Не изменяет AgentRunnerInterface — чистый Decorator pattern.
 */
final readonly class RetryingAgentRunnerService implements RetryingAgentRunnerServiceInterface
{
    public function __construct(
        private AgentRunnerInterface $innerRunner,
        private RetryPolicyVo $retryPolicy,
        private LoggerInterface $logger,
        private ?MetricsCollectorInterface $metrics = null,
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
        $startTime = microtime(true);

        /** @var AgentResultVo|null $lastResult */
        $lastResult = null;
        $lastThrowable = null;
        $attempt = 0;

        while ($attempt <= $this->retryPolicy->getMaxRetries()) {
            $this->recordAttemptMetric($runnerName, $attempt);

            try {
                $result = $this->innerRunner->run($request);
                $lastResult = $result;

                if (!$result->isError()) {
                    return $this->finalizeSuccess($result, $startTime, $runnerName, $attempt);
                }

                // Результат с ошибкой — классифицируем
                $classification = ErrorClassificationVo::createFromClassification($result);

                if (!$classification->shouldRetry()) {
                    $this->logFatalError($runnerName, $result);

                    return $result;
                }

                $lastThrowable = new RuntimeException(
                    $result->getErrorMessage() ?? 'Unknown agent error',
                );
                $this->recordErrorAndLog($runnerName, $attempt, $lastThrowable);
            } catch (Throwable $throwable) {
                $lastThrowable = $throwable;
                $this->recordErrorAndLog($runnerName, $attempt, $throwable);
            }

            $this->waitBeforeNextAttempt($runnerName, $attempt);
            $attempt++;
        }

        return $this->finalizeExhausted($startTime, $runnerName, $lastResult, $lastThrowable);
    }

    /**
     * Фиксирует счётчик попытки вызова runner'а.
     */
    private function recordAttemptMetric(string $runnerName, int $attempt): void
    {
        $this->metrics?->recordCounter('runner.attempt', 1, [
            'runner' => $runnerName,
            'attempt' => (string) ($attempt + 1),
        ]);
    }

    /**
     * Фиксирует счётчик ошибки и логирует неудачную попытку.
     * Используется в двух ветках: выброс исключения и transient error-result.
     */
    private function recordErrorAndLog(string $runnerName, int $attempt, Throwable $throwable): void
    {
        $this->metrics?->recordCounter('runner.error', 1, [
            'runner' => $runnerName,
            'attempt' => (string) ($attempt + 1),
        ]);
        $this->logRetryAttempt($attempt, $throwable);
    }

    /**
     * Логирует fatal-ошибку runner'а и пропускает retry.
     */
    private function logFatalError(string $runnerName, AgentResultVo $result): void
    {
        $this->logger->warning(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" fatal error (exitCode=%d), skipping retry.',
                $runnerName,
                $result->getExitCode(),
            ),
        );
    }

    /**
     * При наличии следующей попытки — выдерживает паузу exponential backoff.
     */
    private function waitBeforeNextAttempt(string $runnerName, int $attempt): void
    {
        if ($attempt >= $this->retryPolicy->getMaxRetries()) {
            return;
        }

        $delayMs = $this->retryPolicy->getDelayForAttempt($attempt);
        $this->logger->debug(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" waiting %dms before attempt %d/%d.',
                $runnerName,
                $delayMs,
                $attempt + 2,
                $this->retryPolicy->getMaxRetries() + 1,
            ),
        );
        usleep($delayMs * 1000);
    }

    /**
     * Завершает выполнение успешным результатом: метрика duration=success и
     * info-лог, если успех достигнут не с первой попытки.
     */
    private function finalizeSuccess(
        AgentResultVo $result,
        float $startTime,
        string $runnerName,
        int $attempt,
    ): AgentResultVo {
        $this->recordDuration($startTime, $runnerName, 'success');

        if ($attempt > 0) {
            $this->logger->info(
                sprintf(
                    '[RetryingAgentRunner] Runner "%s" succeeded on attempt %d.',
                    $runnerName,
                    $attempt + 1,
                ),
            );
        }

        return $result;
    }

    /**
     * Завершает выполнение после исчерпания всех попыток: метрика duration=exhausted,
     * warning и AgentResultVo с пробросом флага timeout последнего результата.
     */
    private function finalizeExhausted(
        float $startTime,
        string $runnerName,
        ?AgentResultVo $lastResult,
        ?Throwable $lastThrowable,
    ): AgentResultVo {
        $this->recordDuration($startTime, $runnerName, 'exhausted');

        $this->logger->warning(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" exhausted all %d attempts. Last error: %s',
                $runnerName,
                $this->retryPolicy->getMaxRetries() + 1,
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
        );

        return AgentResultVo::createError(
            errorMessage: sprintf(
                'All %d attempts exhausted for runner "%s". Last error: %s',
                $this->retryPolicy->getMaxRetries() + 1,
                $runnerName,
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
            timedOut: $lastResult?->isTimedOut() ?? false,
        );
    }

    private function logRetryAttempt(int $attempt, Throwable $throwable): void
    {
        $this->logger->warning(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" attempt %d/%d failed: %s',
                $this->innerRunner->getName(),
                $attempt + 1,
                $this->retryPolicy->getMaxRetries() + 1,
                $throwable->getMessage(),
            ),
        );
    }

    /**
     * Записывает timing-метрику общей длительности выполнения runner'а.
     */
    private function recordDuration(float $startTime, string $runnerName, string $result): void
    {
        $duration = microtime(true) - $startTime;
        $this->metrics?->recordTiming('runner.duration', $duration, [
            'runner' => $runnerName,
            'result' => $result,
        ]);
    }
}
