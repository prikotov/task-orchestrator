<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\AgentRunner;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\Service\AgentRunner\RetryableRunnerFactoryInterface;
use TasK\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Фабрика для создания RetryingAgentRunner.
 *
 * Инкапсулирует создание Infrastructure-декоратора,
 * позволяя Application-слою не зависеть от конкретной реализации.
 */
final readonly class RetryableRunnerFactory implements RetryableRunnerFactoryInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
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

        return new RetryingAgentRunner(
            $runner,
            $retryPolicy,
            $this->logger ?? new NullLogger(),
        );
    }
}
