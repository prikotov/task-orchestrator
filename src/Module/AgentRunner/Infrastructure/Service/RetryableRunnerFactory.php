<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;

/**
 * Фабрика для создания RetryingAgentRunnerService.
 *
 * Инкапсулирует создание Infrastructure-декоратора,
 * позволяя Application-слою не зависеть от конкретной реализации.
 */
final readonly class RetryableRunnerFactory implements RetryableRunnerFactoryInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    #[Override]
    public function createRetryableRunner(
        AgentRunnerInterface $runner,
        RetryPolicyVo $retryPolicy,
    ): AgentRunnerInterface {
        if (!$retryPolicy->isEnabled()) {
            return $runner;
        }

        return new RetryingAgentRunnerService(
            $runner,
            $retryPolicy,
            $this->logger ?? new NullLogger(),
            $this->metrics,
        );
    }
}
