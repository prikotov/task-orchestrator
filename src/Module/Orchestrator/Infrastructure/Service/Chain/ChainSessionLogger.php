<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo;

/**
 * Логгер сессии оркестрации — фасад, делегирующий Writer/Reader.
 *
 * Структура директории сессии:
 *   var/agent/chains/{chainName}/{YYYY-MM-DD_HH-MM-SS}/
 *     topic.md
 *     session.json
 *     step_001_round_001_role_1_system.md
 *     step_001_round_001_role_3_user.md
 *     step_001_round_001_role_4_response.md
 *     ...
 *     result.md
 *
 * @see ChainSessionWriter
 * @see ChainSessionReader
 * @see ChainSessionFileStorage
 * @see ChainSessionBudgetFormatter
 */
final class ChainSessionLogger implements ChainSessionLoggerInterface
{
    private readonly ChainSessionWriter $writer;

    private readonly ChainSessionReader $reader;

    public function __construct(
        string $chainsSessionDir,
        string $basePath,
    ) {
        $storage = new ChainSessionFileStorage();
        $budgetFormatter = new ChainSessionBudgetFormatter();

        $this->writer = new ChainSessionWriter(
            $storage,
            $budgetFormatter,
            rtrim($chainsSessionDir, '/'),
            rtrim($basePath, '/'),
        );
        $this->reader = new ChainSessionReader();
    }

    #[Override]
    public function startSession(
        string $chainName,
        string $topic,
        string $facilitator,
        array $participants,
        int $maxRounds,
    ): string {
        return $this->writer->startSession($chainName, $topic, $facilitator, $participants, $maxRounds);
    }

    #[Override]
    public function logRound(
        int $step,
        int $round,
        string $role,
        bool $isFacilitator,
        string $systemPrompt,
        string $userPrompt,
        string $response,
        float $duration,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        ?string $invocation = null,
    ): void {
        $this->writer->logRound(
            $step,
            $round,
            $role,
            $isFacilitator,
            $systemPrompt,
            $userPrompt,
            $response,
            $duration,
            $inputTokens,
            $outputTokens,
            $cost,
            $invocation,
        );
    }

    #[Override]
    public function completeSession(
        ?string $synthesis,
        float $totalTime,
        int $totalInputTokens,
        int $totalOutputTokens,
        float $totalCost,
        int $totalSteps,
        string $reason = 'facilitator_done',
    ): void {
        $this->writer->completeSession(
            $synthesis,
            $totalTime,
            $totalInputTokens,
            $totalOutputTokens,
            $totalCost,
            $totalSteps,
            $reason,
        );
    }

    #[Override]
    public function setBudget(?BudgetVo $budget): void
    {
        $this->writer->setBudget($budget);
    }

    #[Override]
    public function interruptSession(string $reason = ''): void
    {
        $this->writer->interruptSession($reason);
    }

    #[Override]
    public function resumeSession(string $sessionDir): void
    {
        $this->writer->resumeSession($sessionDir);
    }

    #[Override]
    public function getResumedState(): ?ChainSessionStateVo
    {
        return $this->writer->getResumedState();
    }

    #[Override]
    public function getResponseFilePaths(int $upToStep): array
    {
        return $this->reader->getResponseFilePaths(
            $this->writer->getCurrentSessionDir(),
            $this->writer->getBasePath(),
            $this->writer->getRoundFiles(),
            $upToStep,
        );
    }

    #[Override]
    public function getRoundFiles(): array
    {
        return $this->reader->getRoundFiles($this->writer->getRoundFiles());
    }

    #[Override]
    public function writeContextFile(string $name, string $content): string
    {
        return $this->writer->writeContextFile($name, $content);
    }

    #[Override]
    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string
    {
        return $this->writer->writePromptFile($step, $round, $role, $content, $suffix);
    }

    #[Override]
    public function logInvocation(array $invocation): void
    {
        $this->writer->logInvocation($invocation);
    }

    #[Override]
    public function updateSessionState(int $completedRounds): void
    {
        $this->writer->updateSessionState($completedRounds);
    }
}
