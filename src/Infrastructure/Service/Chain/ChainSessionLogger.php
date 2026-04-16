<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\Chain;

use TasK\Orchestrator\Domain\Service\Chain\ChainSessionLoggerInterface;
use TasK\Orchestrator\Domain\ValueObject\BudgetVo;
use TasK\Orchestrator\Domain\ValueObject\ChainSessionStateVo;
use DateTimeImmutable;
use Override;
use RuntimeException;

/**
 * Логгер сессии оркестрации — запись в var/agent/chains/.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 *
 * Структура директории сессии:
 *   var/agent/chains/{chainName}/{YYYY-MM-DD_HH-MM-SS}/
 *     topic.md
 *     session.json
 *     round_001_role_system.md
 *     round_001_role_user.md
 *     round_001_role_response.md
 *     round_002_role_system.md
 *     round_002_role_user.md
 *     round_002_role_response.md
 *     ...
 *     result.md
 */
final class ChainSessionLogger implements ChainSessionLoggerInterface
{
    private string $basePath = '';

    private ?string $currentSessionDir = null;

    private ?ChainSessionStateVo $resumedState = null;

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

    private ?BudgetVo $budget = null;

    private string $chainsSessionDir = '';

    public function __construct(
        string $chainsSessionDir,
        string $basePath,
    ) {
        $this->chainsSessionDir = rtrim($chainsSessionDir, '/');
        $this->basePath = rtrim($basePath, '/');
    }

    #[Override]
    public function startSession(
        string $chainName,
        string $topic,
        string $facilitator,
        array $participants,
        int $maxRounds,
    ): string {
        $this->chainName = $chainName;
        $this->topic = $topic;
        $this->facilitator = $facilitator;
        $this->participants = $participants;
        $this->maxRounds = $maxRounds;

        $now = new DateTimeImmutable('now');
        $dirName = $now->format('Y-m-d_H-i-s');
        $sessionDir = sprintf('%s/%s/%s', $this->chainsSessionDir, $chainName, $dirName);

        $this->createDirectory($sessionDir);
        $this->currentSessionDir = $sessionDir;

        $this->writeTopic($topic);
        $this->writeContextFile('discussion_history.md', '');
        $this->writeContextFile('facilitator_journal.md', '');
        $this->writeSessionJson('in_progress', 0);

        return $sessionDir;
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
        if ($this->currentSessionDir === null) {
            throw new RuntimeException('No active session. Call startSession() first.');
        }

        $baseName = $this->buildStepBaseName($step, $round, $role);

        // System prompt уже записан через writePromptFile() —
        // проверяем и пишем только если файл почему-то отсутствует.
        $systemFile = $baseName . '_1_system.md';
        if (!file_exists($this->currentSessionDir . '/' . $systemFile)) {
            $this->writeFile($this->currentSessionDir . '/' . $systemFile, $systemPrompt);
        }

        // User prompt (файл #2)
        $this->writeFile($this->currentSessionDir . '/' . $baseName . '_2_user.md', $userPrompt);

        // Response (файл #3)
        $this->writeFile($this->currentSessionDir . '/' . $baseName . '_3_response.md', $response);

        // Сохраняем пути + метрики для session.json
        $roundData = [
            'system' => $systemFile,
            'user' => $baseName . '_2_user.md',
            'response' => $baseName . '_3_response.md',
            'role' => $role,
            'is_facilitator' => $isFacilitator,
            'round' => $round,
            'duration' => round($duration, 1),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => round($cost, 4),
        ];

        if ($invocation !== null) {
            $roundData['invocation'] = $invocation;
        }

        $this->roundFiles[$step] = $roundData;
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
        if ($this->currentSessionDir === null) {
            throw new RuntimeException('No active session.');
        }

        $this->writeSessionJson('completed', $totalSteps, $reason);

        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');

        $synthesisContent = sprintf(
            "# %s: %s\n\n## Synthesis\n%s\n\n## Metrics\n"
            . "- Total rounds: %d\n- Total time: %.1fs\n"
            . "- Total tokens: %d / %d\n- Total cost: \$%.4f\n"
            . "- Completed at: %s\n",
            ucfirst($this->chainName),
            $this->topic,
            $synthesis ?? '(no synthesis)',
            $totalSteps,
            $totalTime,
            $totalInputTokens,
            $totalOutputTokens,
            $totalCost,
            $now,
        );

        $this->writeFile($this->currentSessionDir . '/result.md', $synthesisContent);
    }

    #[Override]
    public function setBudget(?BudgetVo $budget): void
    {
        $this->budget = $budget;

        if ($this->currentSessionDir !== null) {
            $this->writeSessionJson('in_progress', 0);
        }
    }

    #[Override]
    public function interruptSession(string $reason = ''): void
    {
        if ($this->currentSessionDir === null) {
            return;
        }

        $this->writeSessionJson('interrupted', 0, $reason);

        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');

        $budgetInfo = ($reason === 'budget_exceeded' && $this->budget !== null) ? sprintf(
            "\n- Budget limit: %s\n- Max cost per step: %s%s",
            $this->budget->getMaxCostTotal() !== null
                ? sprintf('$%.2f', $this->budget->getMaxCostTotal()) : 'unlimited',
            $this->budget->getMaxCostPerStep() !== null
                ? sprintf('$%.2f', $this->budget->getMaxCostPerStep()) : 'unlimited',
            $this->formatPerRoleBudgetInfo(),
        ) : '';

        $this->writeFile(
            $this->currentSessionDir . '/result.md',
            sprintf(
                "# %s: %s\n\n## Interrupted\n"
                . "- Reason: %s%s\n- Interrupted at: %s\n",
                ucfirst($this->chainName),
                $this->topic,
                $reason !== '' ? $reason : 'unknown',
                $budgetInfo,
                $now,
            ),
        );
    }

    #[Override]
    public function resumeSession(string $sessionDir): void
    {
        if (!is_dir($sessionDir)) {
            throw new RuntimeException(sprintf('Session directory not found: %s', $sessionDir));
        }

        $this->currentSessionDir = $sessionDir;
        $data = json_decode($this->readFile($sessionDir . '/session.json'), true);
        if ($data === null) {
            throw new RuntimeException(sprintf('Invalid session.json in %s', $sessionDir));
        }

        $this->chainName = $data['chain'] ?? '';
        $this->facilitator = $data['facilitator'] ?? '';
        $this->participants = $data['participants'] ?? [];
        $this->maxRounds = $data['max_rounds'] ?? 0;

        foreach ($data['rounds'] ?? [] as $rd) {
            $step = (int)($rd['step'] ?? 0);
            $this->roundFiles[$step] = [
                'system' => $rd['system_prompt_file'] ?? '', 'user' => $rd['user_prompt_file'] ?? '',
                'response' => $rd['response_file'] ?? '', 'role' => $rd['role'] ?? '',
                'is_facilitator' => $rd['is_facilitator'] ?? false, 'round' => (int)($rd['round'] ?? 0),
                'duration' => (float)($rd['duration'] ?? 0), 'input_tokens' => (int)($rd['input_tokens'] ?? 0),
                'output_tokens' => (int)($rd['output_tokens'] ?? 0), 'cost' => (float)($rd['cost'] ?? 0),
            ];
        }

        $readFile = fn(string $key): string => ($resolved = $this->resolvePath($sessionDir, $data[$key] ?? '')) !== '' && file_exists($resolved) ? $this->readFile($resolved) : '';
        $topicContent = $readFile('topic_file');
        $this->topic = $topicContent;

        if (isset($data['budget']) && is_array($data['budget'])) {
            $this->budget = BudgetVo::fromArray($data['budget']);
        }

        $this->resumedState = new ChainSessionStateVo(
            topic: $topicContent,
            facilitator: $this->facilitator,
            participants: $this->participants,
            maxRounds: $this->maxRounds,
            completedRounds: $data['completed_rounds'] ?? 0,
            discussionHistory: $readFile('discussion_history_file'),
            facilitatorJournal: $readFile('facilitator_journal_file'),
        );
    }

    #[Override]
    public function getResumedState(): ?ChainSessionStateVo
    {
        return $this->resumedState;
    }

    #[Override]
    public function getResponseFilePaths(int $upToStep): array
    {
        if ($this->currentSessionDir === null) {
            return [];
        }

        $paths = [];
        foreach ($this->roundFiles as $step => $data) {
            if ($step <= $upToStep && !$data['is_facilitator']) {
                $relative = substr($this->currentSessionDir, strlen($this->basePath) + 1)
                    . '/' . $data['response'];
                $paths[] = $relative;
            }
        }

        return $paths;
    }

    #[Override]
    public function getRoundFiles(): array
    {
        return $this->roundFiles;
    }

    #[Override]
    public function writeContextFile(string $name, string $content): string
    {
        if ($this->currentSessionDir === null) {
            throw new RuntimeException('No active session. Call startSession() first.');
        }

        $this->writeFile($this->currentSessionDir . '/' . $name, $content);

        return $this->currentSessionDir . '/' . $name;
    }

    #[Override]
    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string
    {
        if ($this->currentSessionDir === null) {
            throw new RuntimeException('No active session. Call startSession() first.');
        }

        $baseName = $this->buildStepBaseName($step, $round, $role);
        $fileName = $baseName . $suffix;

        $this->writeFile($this->currentSessionDir . '/' . $fileName, $content);

        return $this->currentSessionDir . '/' . $fileName;
    }

    /**
     * Сохраняет параметры запуска команды в session.json.
     *
     * @param array<string, mixed> $invocation
     */
    #[Override]
    public function logInvocation(array $invocation): void
    {
        $this->invocation = $invocation;

        if ($this->currentSessionDir !== null) {
            $this->writeSessionJson('in_progress', 0);
        }
    }

    /**
     * Обновляет session.json с текущим состоянием сессии.
     *
     * Контент (discussion_history, facilitator_journal) уже записан
     * через writeContextFile(). В session.json хранятся только пути.
     */
    #[Override]
    public function updateSessionState(int $completedRounds): void
    {
        if ($this->currentSessionDir === null) {
            return;
        }

        $this->writeSessionJson('in_progress', $completedRounds);
    }

    /**
     * Создаёт директорию рекурсивно.
     */
    private function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $path));
        }
    }

    private function writeTopic(string $topic): void
    {
        $this->writeFile($this->currentSessionDir . '/topic.md', $topic);
    }

    /**
     * @param list<string> $participants
     */
    private function writeSessionJson(
        string $status,
        int $completedSteps,
        string $reason = '',
    ): void {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');

        // Собираем rounds из накопленных roundFiles
        $rounds = [];
        foreach ($this->roundFiles as $step => $files) {
            $entry = [
                'step' => $step,
                'round' => $files['round'],
                'role' => $files['role'],
                'is_facilitator' => $files['is_facilitator'],
                'system_prompt_file' => $files['system'],
                'user_prompt_file' => $files['user'],
                'response_file' => $files['response'],
                'duration' => $files['duration'],
                'input_tokens' => $files['input_tokens'],
                'output_tokens' => $files['output_tokens'],
                'cost' => $files['cost'],
            ];

            if (isset($files['invocation'])) {
                $entry['invocation'] = $files['invocation'];
            }

            $rounds[] = $entry;
        }

        $data = [
            'chain' => $this->chainName,
            'topic_file' => 'topic.md',
            'facilitator' => $this->facilitator,
            'participants' => $this->participants,
            'max_rounds' => $this->maxRounds,
            'completed_steps' => $completedSteps,
            'completed_rounds' => $this->calculateCompletedRounds(),
            'discussion_history_file' => 'discussion_history.md',
            'facilitator_journal_file' => 'facilitator_journal.md',
            'rounds' => $rounds,
            'invocation' => $this->invocation,
            'budget' => $this->budget !== null ? $this->buildBudgetData() : null,
            'status' => $status,
            'completion_reason' => $reason !== '' ? $reason : null,
            'started_at' => $now,
            'updated_at' => $now,
        ];

        $this->writeFile(
            $this->currentSessionDir . '/session.json',
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Вычисляет количество завершённых раундов (макс round из roundFiles).
     */
    private function calculateCompletedRounds(): int
    {
        $maxRound = 0;
        foreach ($this->roundFiles as $files) {
            $maxRound = max($maxRound, $files['round']);
        }

        return $maxRound;
    }

    /**
     * Разрешает путь относительно sessionDir.
     *
     * Если путь уже абсолютный — возвращает как есть.
     * Если относительный — склеивает с $sessionDir.
     */
    private function resolvePath(string $sessionDir, string $path): string
    {
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : $sessionDir . '/' . $path;
    }

    /** @return array{max_cost_total: float|null, max_cost_per_step: float|null, per_role?: array<string, array{max_cost_total: float|null, max_cost_per_step: float|null}>} */
    private function buildBudgetData(): array
    {
        $budget = $this->budget;
        assert($budget !== null);

        $data = ['max_cost_total' => $budget->getMaxCostTotal(), 'max_cost_per_step' => $budget->getMaxCostPerStep()];
        foreach ($budget->getPerRoleBudgets() as $role => $roleBudget) {
            $data['per_role'][$role] = ['max_cost_total' => $roleBudget->getMaxCostTotal(), 'max_cost_per_step' => $roleBudget->getMaxCostPerStep()];
        }

        return $data;
    }

    private function formatPerRoleBudgetInfo(): string
    {
        $budget = $this->budget;
        if ($budget === null || !$budget->hasRoleBudgets()) {
            return '';
        }

        $lines = "\n- Per-role budgets:";
        foreach ($budget->getPerRoleBudgets() as $role => $rb) {
            $lines .= sprintf(
                "\n  - %s: total=%s, step=%s",
                $role,
                $rb->getMaxCostTotal() !== null ? sprintf('$%.2f', $rb->getMaxCostTotal()) : '∞',
                $rb->getMaxCostPerStep() !== null ? sprintf('$%.2f', $rb->getMaxCostPerStep()) : '∞'
            );
        }

        return $lines;
    }

    private function buildStepBaseName(int $step, int $round, string $role): string
    {
        return sprintf(
            'step_%s_round_%s_%s',
            str_pad((string)$step, 3, '0', STR_PAD_LEFT),
            str_pad((string)$round, 3, '0', STR_PAD_LEFT),
            $role,
        );
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Failed to write file: %s', $path));
        }
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read file: %s', $path));
        }

        return $content;
    }
}
