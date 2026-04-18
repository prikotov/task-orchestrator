<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use Override;
use Psr\Log\LoggerInterface;

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
        private PromptFormatterInterface $formatter,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function tryFallbackRunner(
        FallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?ChainRetryPolicyVo $retryPolicy,
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
