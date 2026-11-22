<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Shared\DynamicLoopPromptFormatterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo;

/**
 * Форматирует промпты и собирает артефакты запуска агентов в цепочке.
 */
final readonly class PromptFormatterService implements DynamicLoopPromptFormatterInterface
{
    /**
     * @internal Статический контекст — не входит в DynamicLoopPromptFormatterInterface.
     */
    public function buildStaticContext(
        string $role,
        string $previousOutput,
        string $task,
    ): string {
        return sprintf(
            "[Контекст от предыдущего агента — %s]:\n%s\n\n[Задача]:\n%s",
            $role,
            $previousOutput,
            $task,
        );
    }

    /**
     * Собирает контекст фасилитатора.
     *
     * При непустом дополнении (taskAddendum) добавляет в промпт блок
     * «Дополнение от Лида»: в continue-промпте — через четвёртый плейсхолдер
     * %s (после секции файлов), в start-промпте — суффиксом в конце.
     * Дополнение передаётся последним аргументом sprintf: легаси-шаблоны
     * из трёх плейсхолдеров рендерятся как прежде (лишний аргумент игнорируется).
     * При пустом или отсутствующем дополнении блок не рендерится.
     */
    #[Override]
    public function buildFacilitatorContext(
        string $startPrompt,
        string $continuePrompt,
        string $topic,
        string $facilitatorSummary,
        string $responseFilesList,
        ?string $taskAddendum = null,
    ): string {
        $addendumBlock = self::buildAddendumBlock($taskAddendum);

        $context = ($facilitatorSummary !== '' || $responseFilesList !== '')
            ? sprintf($continuePrompt, $topic, $facilitatorSummary, $responseFilesList, $addendumBlock)
            : sprintf($startPrompt, $topic) . $addendumBlock;

        // Схлопывание лишних пустых строк — ОСОЗНАННОЕ РЕШЕНИЕ, применяется
        // НА ВСЕХ путях рендера без исключений: и в continue-промпте (с блоком
        // дополнения и без него между секциями остаётся 3 переноса подряд),
        // и в start-промпте, и на resume без task / с пустым addendum
        // (блок не рендерится, но контекст нормализуется так же). Так формат
        // промпта не зависит от пути сборки и наличия дополнения.
        return preg_replace('/\n{3,}/', "\n\n", $context) ?? $context;
    }

    /**
     * Блок «Дополнение от Лида»: заголовок + текст; без дополнения — пустая строка.
     */
    private static function buildAddendumBlock(?string $taskAddendum): string
    {
        if ($taskAddendum === null || trim($taskAddendum) === '') {
            return '';
        }

        return sprintf("\n\n[Дополнение от Лида — новая вводная]:\n%s", trim($taskAddendum));
    }

    #[Override]
    public function buildFinalizeContext(
        string $finalizePrompt,
        string $topic,
        string $responseFilesList,
    ): string {
        return sprintf($finalizePrompt, $topic, $responseFilesList);
    }

    #[Override]
    public function buildParticipantUserPrompt(
        string $userPromptTemplate,
        string $topic,
        string $responseFilesList,
        bool $hasPreviousResponses,
        ?string $challenge,
    ): string {
        $userPrompt = sprintf($userPromptTemplate, $topic, $responseFilesList);

        if (!$hasPreviousResponses) {
            $userPrompt = preg_replace(
                '/\n+Выступления предыдущих участников.*$/s',
                '',
                $userPrompt,
            ) ?? $userPrompt;
        }

        if ($challenge !== null) {
            $userPrompt = $challenge . "\n\n" . $userPrompt;
        }

        return $userPrompt;
    }

    /**
     * @param array<string> $command
     * @return list<string>
     */
    #[Override]
    public function resolveSlot(
        array $command,
        string $marker,
        string $sessionFilePath,
        string $fallbackKey,
    ): array {
        $found = false;
        foreach ($command as $i => $value) {
            if ($value === $marker) {
                $command[$i] = $sessionFilePath;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $command[] = $fallbackKey;
            $command[] = $sessionFilePath;
        }

        return array_values($command);
    }

    #[Override]
    public function buildAgentInvocation(
        DynamicLoopRunRequestVo $request,
        string $userPromptFile,
    ): string {
        $command = $request->getCommand();

        $parts = $command !== []
            ? array_map(
                fn(string $arg) => str_starts_with($arg, '/')
                        ? (file_exists($arg) ? basename($arg) : throw new LogicException(
                            sprintf('Prompt file not found: %s', $arg),
                        ))
                        : $arg,
                $command,
            )
            : ['pi', '--mode', 'json', '-p', '--no-session'];

        if ($request->getModel() !== null) {
            $parts[] = '--model';
            $parts[] = $request->getModel();
        }

        if ($request->getTools() === '') {
            $parts[] = '--no-tools';
        } elseif ($request->getTools() !== null) {
            $parts[] = '--tools';
            $parts[] = $request->getTools();
        }

        if ($request->getSystemPrompt() !== null && !in_array('--system-prompt', $parts, true)) {
            $parts[] = '--system-prompt';
            $parts[] = 'system_prompt.txt';
        }

        $parts[] = $userPromptFile;

        if ($request->getWorkingDir() !== null) {
            $parts[] = sprintf('# cwd: %s', $request->getWorkingDir());
        }

        return implode(' ', $parts);
    }

    #[Override]
    public function buildUserPromptFileName(
        int $step,
        int $round,
        string $role,
    ): string {
        $stepPadded = str_pad((string)$step, 3, '0', STR_PAD_LEFT);
        $roundPadded = str_pad((string)$round, 3, '0', STR_PAD_LEFT);

        return sprintf('step_%s_round_%s_%s_2_user.md', $stepPadded, $roundPadded, $role);
    }
}
