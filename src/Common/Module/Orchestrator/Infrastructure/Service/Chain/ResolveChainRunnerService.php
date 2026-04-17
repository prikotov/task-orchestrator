<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Резолвит эффективный runner: retry-декоратор и fallback при ошибке.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo Разбить tryFallbackRunner на buildFallbackRequest + executeFallback — TASK-agent-orchestrator-decompose-step2.
 */
final readonly class ResolveChainRunnerService implements ResolveChainRunnerServiceInterface
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $runnerRegistry,
        private RetryableRunnerFactoryInterface $retryableRunnerFactory,
        private PromptFormatterInterface $formatter,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function createRunnerWithRetry(
        AgentRunnerInterface $runner,
        ?RetryPolicyVo $retryPolicy,
    ): AgentRunnerInterface {
        if ($retryPolicy === null || !$retryPolicy->isEnabled()) {
            return $runner;
        }

        return $this->retryableRunnerFactory->createRetryableRunner(
            $runner,
            $retryPolicy,
        );
    }

    #[Override]
    public function tryFallbackRunner(
        FallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?RetryPolicyVo $retryPolicy,
        AgentRunRequestVo $primaryRequest,
        ?string $promptFile = null,
    ): ?AgentResultVo {
        $fallbackRunnerName = $fallbackConfig->getRunnerName();
        if ($fallbackRunnerName === null) {
            return null;
        }

        $this->logger?->warning(sprintf(
            '[ResolveChainRunnerService] Runner "%s" failed for role "%s", trying fallback "%s".',
            $primaryRunnerName,
            $role,
            $fallbackRunnerName,
        ));

        try {
            $fallbackRunner = $this->runnerRegistry->get($fallbackRunnerName);
        } catch (\Throwable $e) {
            $this->logger?->error(sprintf(
                '[ResolveChainRunnerService] Fallback runner "%s" not found: %s',
                $fallbackRunnerName,
                $e->getMessage(),
            ));

            return null;
        }

        $fallbackCommand = $fallbackConfig->getCommand();
        if ($promptFile !== null) {
            $fallbackCommand = $this->formatter->resolveSlot(
                $fallbackCommand,
                '@system-prompt',
                $promptFile,
                '--system-prompt',
            );
        }

        $fallbackRequest = new AgentRunRequestVo(
            role: $primaryRequest->getRole(),
            task: $primaryRequest->getTask(),
            systemPrompt: $primaryRequest->getSystemPrompt(),
            previousContext: $primaryRequest->getPreviousContext(),
            model: $primaryRequest->getModel(),
            tools: $primaryRequest->getTools(),
            workingDir: $primaryRequest->getWorkingDir(),
            timeout: $primaryRequest->getTimeout(),
            command: $fallbackCommand,
        );

        $effectiveFallbackRunner = $this->createRunnerWithRetry($fallbackRunner, $retryPolicy);

        try {
            $result = $effectiveFallbackRunner->run($fallbackRequest->withTruncatedContext());

            if ($result->isError()) {
                $this->logger?->error(sprintf(
                    '[ResolveChainRunnerService] Fallback runner "%s" also failed for role "%s": %s',
                    $fallbackRunnerName,
                    $role,
                    $result->getErrorMessage() ?? 'unknown',
                ));
            }

            if (!$result->isError()) {
                $this->logger?->info(sprintf(
                    '[ResolveChainRunnerService] Fallback runner "%s" succeeded for role "%s".',
                    $fallbackRunnerName,
                    $role,
                ));
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error(sprintf(
                '[ResolveChainRunnerService] Fallback runner "%s" threw exception for role "%s": %s',
                $fallbackRunnerName,
                $role,
                $e->getMessage(),
            ));

            return null;
        }
    }
}
