<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\AgentRunner;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\ValueObject\AgentResultVo;
use TasK\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TasK\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Декоратор AgentRunnerInterface — добавляет retry с exponential backoff.
 *
 * Оборачивает любой AgentRunnerInterface и при выбросе исключения
 * повторяет вызов с задержкой по exponential backoff.
 * После исчерпания всех попыток возвращает AgentResultVo::createFromError().
 *
 * Не изменяет AgentRunnerInterface — чистый Decorator pattern.
 */
final readonly class RetryingAgentRunner implements AgentRunnerInterface
{
    public function __construct(
        private AgentRunnerInterface $innerRunner,
        private RetryPolicyVo $retryPolicy,
        private LoggerInterface $logger,
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

        while ($attempt <= $this->retryPolicy->getMaxRetries()) {
            try {
                $result = $this->innerRunner->run($request);

                if (!$result->isError()) {
                    if ($attempt > 0) {
                        $this->logger->info(
                            sprintf(
                                '[RetryingAgentRunner] Runner "%s" succeeded on attempt %d.',
                                $this->innerRunner->getName(),
                                $attempt + 1,
                            ),
                        );
                    }

                    return $result;
                }

                // Результат с ошибкой (не исключение) — тоже retry
                $lastThrowable = new RuntimeException(
                    $result->getErrorMessage() ?? 'Unknown agent error',
                );

                $this->logRetryAttempt($attempt, $lastThrowable);
            } catch (Throwable $throwable) {
                $lastThrowable = $throwable;
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

        $this->logger->warning(
            sprintf(
                '[RetryingAgentRunner] Runner "%s" exhausted all %d attempts. Last error: %s',
                $this->innerRunner->getName(),
                $this->retryPolicy->getMaxRetries() + 1,
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
        );

        return AgentResultVo::createFromError(
            errorMessage: sprintf(
                'All %d attempts exhausted for runner "%s". Last error: %s',
                $this->retryPolicy->getMaxRetries() + 1,
                $this->innerRunner->getName(),
                $lastThrowable?->getMessage() ?? 'unknown',
            ),
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
}
