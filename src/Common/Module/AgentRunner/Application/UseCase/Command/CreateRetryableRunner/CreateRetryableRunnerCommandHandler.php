<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\CreateRetryableRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;

/**
 * Обработчик команды создания retryable runner'а.
 *
 * Оборачивает runner в retry-декоратор с заданной политикой.
 */
final readonly class CreateRetryableRunnerCommandHandler
{
    public function __construct(
        private RetryableRunnerFactoryInterface $retryableRunnerFactory,
    ) {
    }

    public function handle(
        AgentRunnerInterface $runner,
        int $maxRetries,
        int $initialDelayMs = 1000,
        int $maxDelayMs = 30000,
        float $multiplier = 2.0,
    ): CreateRetryableRunnerResultDto {
        $retryPolicy = new RetryPolicyVo(
            maxRetries: $maxRetries,
            initialDelayMs: $initialDelayMs,
            maxDelayMs: $maxDelayMs,
            multiplier: $multiplier,
        );

        $retryableRunner = $this->retryableRunnerFactory->createRetryableRunner($runner, $retryPolicy);

        return new CreateRetryableRunnerResultDto($retryableRunner);
    }
}
