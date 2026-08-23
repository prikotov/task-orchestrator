<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi;

use InvalidArgumentException;
use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\PiAgentRunnerServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleServiceInterface;

/**
 * Реализация AgentRunnerInterface для pi CLI.
 *
 * Запускает pi через Symfony Process.
 * Если в AgentRunRequestVo задан command — используется он как базовая команда.
 * Иначе — стандартная: `pi --mode json -p` с сохранением сессии.
 * Пути к файлам промптов (--system-prompt, --append-system-prompt) передаются
 * как абсолютные пути — Pi читает файлы самостоятельно через existsSync-эвристику.
 * Значения с префиксом @ разрешаются как пути к файлам (содержимое подставляется inline).
 *
 * Runtime-жизненный цикл (создание Process, прокси-окружение, буферизация
 * stdout, liveness-ожидание, остановка) делегирован общему
 * {@see RunAgentProcessLifecycleServiceInterface}; здесь остаётся только
 * Pi-специфика: buildCommand и buildResult с проверкой isError/errorMessage.
 *
 * Поддержка HTTPS-прокси через HttpsProxyBridge (переиспользуется из Codex-раннера):
 * если CODEX_HTTP_PROXY содержит https:// схему, автоматически запускается
 * локальный HTTP-прокси-мост, пересылающий CONNECT-запросы через TLS.
 * Симметрично CodexAgentRunnerService, чтобы любой pi-профиль, ходящий к OpenAI,
 * работал через прокси без ручных действий.
 */
final readonly class PiAgentRunnerService implements PiAgentRunnerServiceInterface
{
    public function __construct(
        private PiJsonlParser $parser,
        private RunAgentProcessLifecycleServiceInterface $processLifecycle,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return 'pi';
    }

    #[Override]
    public function isAvailable(): bool
    {
        $process = new Process(['which', 'pi']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Строит массив CLI-команды для запуска pi по AgentRunRequestVo.
     *
     * Последовательно добавляет: базовую команду, runner args,
     * model, tools, system prompt, флаг -nc и user prompt.
     *
     * @param AgentRunRequestVo $request запрос на запуск агента
     *
     * @return list<string> готовый массив аргументов для Symfony Process
     *
     * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
     * (pre-existing latent: AgentRunRequestVo::getCommand/getRunnerArgs возвращают
     * array без element-type → psalm выводит mixed; не относится к этому PR)
     */
    #[Override]
    public function buildCommand(AgentRunRequestVo $request): array
    {
        $command = $request->getCommand();

        if ($command === []) {
            $command = ['pi', '--mode', 'json', '-p'];
        } elseif ($command[0] !== 'pi' && !str_contains($command[0], 'pi')) {
            throw new InvalidArgumentException(sprintf(
                'AgentRunRequestVo::$command must be either empty (runner default) or a full CLI command starting with an executable. '
                . 'Got: %s',
                implode(' ', $command),
            ));
        }

        // Доп. аргументы runner'а (prompt-файлы от dynamic loop)
        foreach ($request->getRunnerArgs() as $arg) {
            $command[] = $arg;
        }

        // Разрешение @file → содержимое файла
        $command = $this->resolveCommandFiles($command, $request->getWorkingDir());

        // Разрешение маркеров @system-prompt / @append-system-prompt → путь к файлу
        // (pi читает файл сам через --system-prompt/--append-system-prompt).
        // Отдельно от resolveCommandFiles (который про @file → inline-содержимое):
        // маркеры — это слоты prompt_file роли, резолвятся из request, не из FS.
        $command = $this->resolvePromptMarkers($command, $request);

        // Model — только если не задан в command
        if ($request->getModel() !== null && !in_array('--model', $command, true)) {
            $command[] = '--model';
            $command[] = $request->getModel();
        }

        // Tools — только если не задан в command
        if ($request->getTools() === '' && !in_array('--no-tools', $command, true)) {
            $command[] = '--no-tools';
        } elseif ($request->getTools() !== null && !in_array('--tools', $command, true)) {
            $command[] = '--tools';
            $command[] = $request->getTools();
        }

        // System prompt — только если не задан в command
        if ($request->getSystemPrompt() !== null && !in_array('--system-prompt', $command, true)) {
            $command[] = '--system-prompt';
            $command[] = $request->getSystemPrompt();
        }

        // No context files — отключить загрузку AGENTS.md / CLAUDE.md
        if ($request->hasNoContextFiles() && !in_array('-nc', $command, true) && !in_array('-no-context-files', $command, true)) {
            $command[] = '-nc';
        }

        // User prompt: previous context + task
        $prompt = $this->processLifecycle->buildUserPrompt($request);
        $command[] = $prompt;

        return $command;
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        return $this->processLifecycle->run(
            request: $request,
            runnerName: 'pi',
            buildCommand: fn (AgentRunRequestVo $request): array => $this->buildCommand($request),
            resetParser: fn () => $this->parser->reset(),
            feedParserLine: fn (string $line) => $this->parser->feed($line),
            buildResult: fn (Process $process, string $errorOutput): AgentResultVo => $this->buildResult($process, $errorOutput),
        );
    }

    /**
     * Формирует итоговый AgentResultVo по завершившемуся процессу.
     *
     * Неуспешный exit — ошибка с stderr-tail (или кодом выхода);
     * успешный — метрики из накопленного JSONL-парсера. Если сам агент
     * сообщил об ошибке в JSONL-потоке (isError), она приоритетнее exit-кода.
     */
    private function buildResult(Process $process, string $errorOutput): AgentResultVo
    {
        if (!$process->isSuccessful()) {
            return AgentResultVo::createError(
                $errorOutput !== '' ? $errorOutput : sprintf('pi exited with code %d.', $process->getExitCode() ?? 1),
                $process->getExitCode() ?? 1,
            );
        }

        $parsed = $this->parser->result();

        if ($parsed['isError']) {
            return AgentResultVo::createError(
                errorMessage: $parsed['errorMessage'],
            );
        }

        return AgentResultVo::createSuccess(
            outputText: $parsed['outputText'],
            inputTokens: $parsed['inputTokens'],
            outputTokens: $parsed['outputTokens'],
            cacheReadTokens: $parsed['cacheReadTokens'],
            cacheWriteTokens: $parsed['cacheWriteTokens'],
            cost: $parsed['cost'],
            model: $parsed['model'],
            turns: $parsed['turns'],
        );
    }

    /**
     * Резолвит маркеры @system-prompt и @append-system-prompt в элементах command
     * в пути к prompt-файлам роли (pi читает файлы сам, как codex model_instructions_file).
     *
     * Симметрично CodexAgentRunnerService::resolvePromptSlots. Источники путей:
     * - @system-prompt → request.systemPrompt (если это путь к существующему файлу)
     * - @append-system-prompt → runnerArgs[--append-system-prompt] (если значение — путь)
     *
     * @param list<string> $command
     *
     * @return list<string>
     */
    private function resolvePromptMarkers(array $command, AgentRunRequestVo $request): array
    {
        $systemPath = $this->processLifecycle->resolveSystemPromptPath($request);
        $appendPath = $this->extractAppendPromptPath($request->getRunnerArgs());

        $resolved = [];
        foreach ($command as $value) {
            if ($systemPath !== null && $value === '@system-prompt') {
                $resolved[] = $systemPath;
                continue;
            }
            if ($appendPath !== null && $value === '@append-system-prompt') {
                $resolved[] = $appendPath;
                continue;
            }
            $resolved[] = $value;
        }

        return $resolved;
    }

    /**
     * Извлекает путь к append-system-prompt-файлу из runnerArgs.
     *
     * @param list<string> $runnerArgs
     *
     * @return string|null путь к существующему файлу или null
     */
    private function extractAppendPromptPath(array $runnerArgs): ?string
    {
        for ($i = 0, $count = count($runnerArgs); $i < $count - 1; $i++) {
            if ($runnerArgs[$i] === '--append-system-prompt') {
                $path = $runnerArgs[$i + 1];

                return (file_exists($path) && is_file($path)) ? $path : null;
            }
        }

        return null;
    }

    /**
     * Разрешает @file в элементах command.
     *
     * Формат: `@path/to/file.txt` → содержимое файла.
     * Если файл не найден — значение остаётся как есть.
     *
     * @param list<string> $command
     * @param string|null $workingDir базовая директория для относительных путей
     *
     * @return list<string>
     */
    private function resolveCommandFiles(array $command, ?string $workingDir): array
    {
        $resolved = [];

        foreach ($command as $value) {
            if (str_starts_with($value, '@')) {
                $path = substr($value, 1);

                if ($workingDir !== null && !str_starts_with($path, '/')) {
                    $path = $workingDir . '/' . $path;
                }

                if (file_exists($path)) {
                    $content = file_get_contents($path);
                    if ($content !== false) {
                        $resolved[] = trim($content);
                        continue;
                    }
                }
            }

            $resolved[] = $value;
        }

        return $resolved;
    }
}
