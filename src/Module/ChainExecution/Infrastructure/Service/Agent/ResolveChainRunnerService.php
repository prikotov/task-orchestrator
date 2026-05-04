<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;

/**
 * Резолвит fallback runner при ошибке основного.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo Разбить tryFallbackRunner на buildFallbackRequest + executeFallback — TASK-agent-orchestrator-decompose-step2.
 */
final readonly class ResolveChainRunnerService implements ResolveChainRunnerServiceInterface
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private FormatPromptServiceInterface $formatter,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function tryFallbackRunner(
        ExecutionFallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?ExecutionRetryPolicyVo $retryPolicy,
        ChainRunRequestVo $primaryRequest,
        ?string $promptFile = null,
    ): ?ChainRunResultVo {
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

        $fallbackCommand = $fallbackConfig->getCommand();
        if ($promptFile !== null) {
            $fallbackCommand = $this->formatter->resolveSlot(
                $fallbackCommand,
                '@system-prompt',
                $promptFile,
                '--system-prompt',
            );
        }

        $fallbackRequest = new ChainRunRequestVo(
            role: $primaryRequest->getRole(),
            task: $primaryRequest->getTask(),
            systemPrompt: $primaryRequest->getSystemPrompt(),
            previousContext: $primaryRequest->getPreviousContext(),
            model: $primaryRequest->getModel(),
            tools: $primaryRequest->getTools(),
            workingDir: $primaryRequest->getWorkingDir(),
            timeout: $primaryRequest->getTimeout(),
            command: $fallbackCommand,
            runnerName: $fallbackRunnerName,
        );

        try {
            $result = $this->agentRunner->run($fallbackRequest->withTruncatedContext(), $retryPolicy);

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
