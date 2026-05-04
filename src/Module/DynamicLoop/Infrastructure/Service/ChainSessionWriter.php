<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use DateTimeImmutable;
use RuntimeException;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;

/**
 * Запись событий сессии оркестрации.
 *
 * Владеет mutable state текущей сессии. Делегирует файловые операции
 * в ChainSessionFileStorage, форматирование бюджета — в ChainSessionBudgetFormatter.
 */
final class ChainSessionWriter
{
    private ?string $currentSessionDir = null;
    private string $chainName = '';
    private string $topic = '';
    private string $facilitator = '';
    /** @var list<string> */
    private array $participants = [];
    private int $maxRounds = 0;
    /** @var array<int, array{system: string, user: string, response: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, invocation?: string}> */
    private array $roundFiles = [];
    /** @var array<string, mixed> */
    private array $invocation = [];
    private ?DynamicLoopBudgetVo $budget = null;
    private ?ChainSessionStateVo $resumedState = null;

    public function __construct(
        private readonly ChainSessionFileStorage $storage,
        private readonly ChainSessionBudgetFormatter $budgetFormatter,
        private readonly string $chainsSessionDir,
        private readonly string $basePath,
    ) {}

    /** @param list<string> $participants */
    public function startSession(string $chainName, string $topic, string $facilitator, array $participants, int $maxRounds): string
    {
        $this->chainName = $chainName;
        $this->topic = $topic;
        $this->facilitator = $facilitator;
        $this->participants = $participants;
        $this->maxRounds = $maxRounds;
        $dirName = (new DateTimeImmutable('now'))->format('Y-m-d_H-i-s');
        $sessionDir = sprintf('%s/%s/%s', $this->chainsSessionDir, $chainName, $dirName);
        $this->storage->createDirectory($sessionDir);
        $real = realpath($sessionDir);
        $this->currentSessionDir = $real !== false ? $real : $sessionDir;
        $this->storage->writeFile($this->currentSessionDir . '/topic.md', $topic);
        $this->writeContextFile('discussion_history.md', '');
        $this->writeContextFile('facilitator_journal.md', '');
        $this->writeSessionJson('in_progress', 0);

        return $sessionDir;
    }

    public function logRound(int $step, int $round, string $role, bool $isFacilitator, string $systemPrompt, string $userPrompt, string $response, float $duration, int $inputTokens, int $outputTokens, float $cost, ?string $invocation = null): void
    {
        $this->assertActiveSession();
        $dir = $this->currentSessionDir;
        $baseName = $this->storage->buildStepBaseName($step, $round, $role);
        $systemFile = $baseName . '_1_system.md';
        if (!file_exists($dir . '/' . $systemFile)) {
            $this->storage->writeFile($dir . '/' . $systemFile, $systemPrompt);
        }
        $this->storage->writeFile($dir . '/' . $baseName . '_3_user.md', $userPrompt);
        $this->storage->writeFile($dir . '/' . $baseName . '_4_response.md', $response);
        $roundData = [
            'system' => $systemFile, 'user' => $baseName . '_3_user.md',
            'response' => $baseName . '_4_response.md', 'role' => $role,
            'is_facilitator' => $isFacilitator, 'round' => $round,
            'duration' => round($duration, 1), 'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens, 'cost' => round($cost, 4),
        ];
        if ($invocation !== null) {
            $roundData['invocation'] = $invocation;
        }
        $this->roundFiles[$step] = $roundData;
    }

    public function completeSession(?string $synthesis, float $totalTime, int $totalInputTokens, int $totalOutputTokens, float $totalCost, int $totalSteps, string $reason = 'facilitator_done'): void
    {
        $this->assertActiveSession();
        $this->writeSessionJson('completed', $totalSteps, $reason);
        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
        $this->storage->writeFile($this->currentSessionDir . '/result.md', sprintf(
            "# %s: %s\n\n## Synthesis\n%s\n\n## Metrics\n- Total rounds: %d\n- Total time: %.1fs\n- Total tokens: %d / %d\n- Total cost: \$%.4f\n- Completed at: %s\n",
            ucfirst($this->chainName), $this->topic, $synthesis ?? '(no synthesis)',
            $totalSteps, $totalTime, $totalInputTokens, $totalOutputTokens, $totalCost, $now,
        ));
    }

    public function setBudget(?DynamicLoopBudgetVo $budget): void
    {
        $this->budget = $budget;
        if ($this->currentSessionDir !== null) {
            $this->writeSessionJson('in_progress', 0);
        }
    }

    public function interruptSession(string $reason = ''): void
    {
        if ($this->currentSessionDir === null) {
            return;
        }
        $this->writeSessionJson('interrupted', 0, $reason);
        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
        $budgetInfo = ($reason === 'budget_exceeded' && $this->budget !== null) ? sprintf(
            "\n- Budget limit: %s\n- Max cost per step: %s%s",
            $this->budgetFormatter->formatBudgetValue($this->budget->getMaxCostTotal()),
            $this->budgetFormatter->formatBudgetValue($this->budget->getMaxCostPerStep()),
            $this->budgetFormatter->formatPerRoleBudgetInfo($this->budget),
        ) : '';
        $this->storage->writeFile($this->currentSessionDir . '/result.md', sprintf(
            "# %s: %s\n\n## Interrupted\n- Reason: %s%s\n- Interrupted at: %s\n",
            ucfirst($this->chainName), $this->topic,
            $reason !== '' ? $reason : 'unknown', $budgetInfo, $now,
        ));
    }

    public function resumeSession(string $sessionDir): void
    {
        if (!is_dir($sessionDir)) {
            throw new RuntimeException(sprintf('Session directory not found: %s', $sessionDir));
        }
        $real = realpath($sessionDir);
        $this->currentSessionDir = $real !== false ? $real : $sessionDir;
        $data = json_decode($this->storage->readFile($this->currentSessionDir . '/session.json'), true);
        if ($data === null) {
            throw new RuntimeException(sprintf('Invalid session.json in %s', $sessionDir));
        }
        $this->chainName = $data['chain'] ?? '';
        $this->facilitator = $data['facilitator'] ?? '';
        $this->participants = $data['participants'] ?? [];
        $this->maxRounds = $data['max_rounds'] ?? 0;
        $this->roundFiles = $this->parseRoundFiles($data['rounds'] ?? []);
        $this->topic = $this->readSessionFile($sessionDir, $data['topic_file'] ?? '');
        $this->budget = isset($data['budget']) && is_array($data['budget']) ? DynamicLoopBudgetVo::fromArray($data['budget']) : null;
        $this->resumedState = new DynamicLoopSessionStateVo(
            topic: $this->topic, facilitator: $this->facilitator,
            participants: $this->participants, maxRounds: $this->maxRounds,
            completedRounds: $data['completed_rounds'] ?? 0,
            discussionHistory: $this->readSessionFile($sessionDir, $data['discussion_history_file'] ?? ''),
            facilitatorJournal: $this->readSessionFile($sessionDir, $data['facilitator_journal_file'] ?? ''),
        );
    }

    public function writeContextFile(string $name, string $content): string
    {
        $this->assertActiveSession();
        $this->storage->writeFile($this->currentSessionDir . '/' . $name, $content);

        return $this->currentSessionDir . '/' . $name;
    }

    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string
    {
        $this->assertActiveSession();
        $fileName = $this->storage->buildStepBaseName($step, $round, $role) . $suffix;
        $this->storage->writeFile($this->currentSessionDir . '/' . $fileName, $content);

        return $this->currentSessionDir . '/' . $fileName;
    }

    /** @param array<string, mixed> $invocation */
    public function logInvocation(array $invocation): void
    {
        $this->invocation = $invocation;
        if ($this->currentSessionDir !== null) {
            $this->writeSessionJson('in_progress', 0);
        }
    }

    public function updateSessionState(int $completedRounds): void
    {
        if ($this->currentSessionDir !== null) {
            $this->writeSessionJson('in_progress', $completedRounds);
        }
    }

    public function getCurrentSessionDir(): ?string { return $this->currentSessionDir; }

    /**
     * @return array<int, array{system: string, user: string, response: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, invocation?: string}>
     */
    public function getRoundFiles(): array { return $this->roundFiles; }

    public function getBasePath(): string { return $this->basePath; }

    public function getResumedState(): ?ChainSessionStateVo { return $this->resumedState; }

    // --- Internal ---

    private function assertActiveSession(): void
    {
        if ($this->currentSessionDir === null) {
            throw new RuntimeException('No active session. Call startSession() first.');
        }
    }

    /** @param list<array<string, mixed>> $roundsData */
    private function parseRoundFiles(array $roundsData): array
    {
        $result = [];
        foreach ($roundsData as $rd) {
            $step = (int) ($rd['step'] ?? 0);
            $result[$step] = [
                'system' => $rd['system_prompt_file'] ?? '', 'user' => $rd['user_prompt_file'] ?? '',
                'response' => $rd['response_file'] ?? '', 'role' => $rd['role'] ?? '',
                'is_facilitator' => $rd['is_facilitator'] ?? false, 'round' => (int) ($rd['round'] ?? 0),
                'duration' => (float) ($rd['duration'] ?? 0), 'input_tokens' => (int) ($rd['input_tokens'] ?? 0),
                'output_tokens' => (int) ($rd['output_tokens'] ?? 0), 'cost' => (float) ($rd['cost'] ?? 0),
            ];
        }

        return $result;
    }

    private function readSessionFile(string $sessionDir, string $key): string
    {
        $resolved = $this->storage->resolvePath($sessionDir, $key);
        if ($resolved === '' || !file_exists($resolved)) {
            return '';
        }

        return $this->storage->readFile($resolved);
    }

    private function writeSessionJson(string $status, int $completedSteps, string $reason = ''): void
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
        $rounds = [];
        foreach ($this->roundFiles as $step => $files) {
            $entry = [
                'step' => $step, 'round' => $files['round'], 'role' => $files['role'],
                'is_facilitator' => $files['is_facilitator'], 'system_prompt_file' => $files['system'],
                'user_prompt_file' => $files['user'], 'response_file' => $files['response'],
                'duration' => $files['duration'], 'input_tokens' => $files['input_tokens'],
                'output_tokens' => $files['output_tokens'], 'cost' => $files['cost'],
            ];
            if (isset($files['invocation'])) {
                $entry['invocation'] = $files['invocation'];
            }
            $rounds[] = $entry;
        }
        $data = [
            'chain' => $this->chainName, 'topic_file' => 'topic.md',
            'facilitator' => $this->facilitator, 'participants' => $this->participants,
            'max_rounds' => $this->maxRounds, 'completed_steps' => $completedSteps,
            'completed_rounds' => $this->calculateCompletedRounds(),
            'discussion_history_file' => 'discussion_history.md',
            'facilitator_journal_file' => 'facilitator_journal.md',
            'rounds' => $rounds, 'invocation' => $this->invocation,
            'budget' => $this->budget !== null ? $this->budgetFormatter->buildBudgetData($this->budget) : null,
            'status' => $status, 'completion_reason' => $reason !== '' ? $reason : null,
            'started_at' => $now, 'updated_at' => $now,
        ];
        $this->storage->writeFile(
            $this->currentSessionDir . '/session.json',
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function calculateCompletedRounds(): int
    {
        $maxRound = 0;
        foreach ($this->roundFiles as $files) {
            $maxRound = max($maxRound, $files['round']);
        }

        return $maxRound;
    }
}
