<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;
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
final readonly class RetryingAgentRunner implements AgentRunnerInterface
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
        $attempt = 0;
        $lastThrowable = null;
        /** @var AgentResultVo|null $lastResult */
        $lastResult = null;
        $runnerName = $this->innerRunner->getName();
        $startTime = microtime(true);

        while ($attempt <= $this->retryPolicy->getMaxRetries()) {
            $this->metrics?->recordCounter('runner.attempt', 1, [
                'runner' => $runnerName,
                'attempt' => (string) ($attempt + 1),
            ]);

            try {
                $result = $this->innerRunner->run($request);
                $lastResult = $result;

                if (!$result->isError()) {
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

                // Результат с ошибкой — классифицируем
                $classification = ErrorClassificationVo::createFromClassification($result);

                if (!$classification->shouldRetry()) {
                    $this->logger->warning(
                        sprintf(
                            '[RetryingAgentRunner] Runner "%s" fatal error (exitCode=%d), skipping retry.',
                            $this->innerRunner->getName(),
                            $result->getExitCode(),
                        ),
                    );

                    return $result;
                }

                $lastThrowable = new RuntimeException(
                    $result->getErrorMessage() ?? 'Unknown agent error',
                );

                $this->metrics?->recordCounter('runner.error', 1, [
                    'runner' => $runnerName,
                    'attempt' => (string) ($attempt + 1),
                ]);

                $this->logRetryAttempt($attempt, $lastThrowable);
            } catch (Throwable $throwable) {
                $lastThrowable = $throwable;

                $this->metrics?->recordCounter('runner.error', 1, [
                    'runner' => $runnerName,
                    'attempt' => (string) ($attempt + 1),
                ]);

                $this->logRetryAttempt($attempt, $throwable);
            }

            $attempt++;

            if ($attempt <= $this->retryPolicy->getMaxRetries()) {
                $delayMs = $this->retryPolicy->getDelayForAttempt($attempt - 1);
                $this->logger->debug(
                    sprintf(
                        '[RetryingAgentRunner] Runner "%s" waiting %dms before attempt %d/%d.',
                        $this->innerRunner->getName(),
                        $delayMs,
                        $attempt + 1,
                        $this->retryPolicy->getMaxRetries() + 1,
                    ),
                );
                usleep($delayMs * 1000);
            }
        }

        $this->recordDuration($startTime, $runnerName, 'exhausted');

        $this->logger->warning(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" exhausted all %d attempts. Last error: %s',
                $runnerName,
                $this->retryPolicy->getMaxRetries() + 1,
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
        );

        // Если последний результат был timeout — пробрасываем флаг
        $timedOut = $lastResult?->isTimedOut() ?? false;

        return AgentResultVo::createError(
            errorMessage: sprintf(
                'All %d attempts exhausted for runner "%s". Last error: %s',
                $this->retryPolicy->getMaxRetries() + 1,
                $this->innerRunner->getName(),
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
            timedOut: $timedOut,
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
