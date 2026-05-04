<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;

/**
 * Стаб DynamicLoopSessionLoggerInterface для integration-тестов.
 *
 * Создаёт реальную временную директорию для session-логов.
 * Вызывай cleanup() в tearDown() теста для предотвращения утечки /tmp.
 */
class StubSessionLogger implements DynamicLoopSessionLoggerInterface
{
    protected ?string $sessionDir = null;

    #[Override]
    public function startSession(string $chainName, string $topic, string $facilitator, array $participants, int $maxRounds): string
    {
        $this->sessionDir = sys_get_temp_dir() . '/test_session_' . uniqid();
        mkdir($this->sessionDir, 0777, true);

        return $this->sessionDir;
    }

    #[Override]
    public function logRound(int $step, int $round, string $role, bool $isFacilitator, string $systemPrompt, string $userPrompt, string $response, float $duration, int $inputTokens, int $outputTokens, float $cost, ?string $invocation = null): void
    {
        // no-op
    }

    #[Override]
    public function completeSession(?string $synthesis, float $totalTime, int $totalInputTokens, int $totalOutputTokens, float $totalCost, int $totalSteps, string $reason = 'facilitator_done'): void
    {
        // no-op
    }

    #[Override]
    public function setBudget(?DynamicLoopBudgetVo $budget): void
    {
        // no-op
    }

    #[Override]
    public function interruptSession(string $reason = ''): void
    {
        // no-op
    }

    #[Override]
    public function updateSessionState(int $completedRounds): void
    {
        // no-op
    }

    #[Override]
    public function logInvocation(array $invocation): void
    {
        // no-op
    }

    #[Override]
    public function writeContextFile(string $name, string $content): string
    {
        $path = ($this->sessionDir ?? sys_get_temp_dir()) . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    #[Override]
    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string
    {
        $filename = sprintf('step_%03d_round_%03d_%s%s', $step, $round, $role, $suffix);
        $path = ($this->sessionDir ?? sys_get_temp_dir()) . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    #[Override]
    public function resumeSession(string $sessionDir): void
    {
        $this->sessionDir = $sessionDir;
    }

    #[Override]
    public function getResumedState(): ?DynamicLoopSessionStateVo
    {
        return null;
    }

    #[Override]
    public function getResponseFilePaths(int $upToStep): array
    {
        return [];
    }

    #[Override]
    public function getRoundFiles(): array
    {
        return [];
    }

    /**
     * Удаляет временную директорию сессии (рекурсивно).
     *
     * Вызывать в tearDown() теста для предотвращения утечки временных файлов в /tmp.
     */
    public function cleanup(): void
    {
        if ($this->sessionDir !== null && is_dir($this->sessionDir)) {
            $this->removeDirectory($this->sessionDir);
            $this->sessionDir = null;
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
