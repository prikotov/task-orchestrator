<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Connectivity;

use Override;
use RuntimeException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityCommandResolverInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityResolvedCommandVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * Собирает production-like argv (массив аргументов) для проверки связности роли.
 *
 * Подставляет минимальные prompt-файлы/инструкции вместо `@system-prompt` и
 * `@append-system-prompt`, затем добавляет user prompt последним positional argument (позиционным аргументом).
 */
final readonly class ProductionLikeConnectivityCommandResolverService implements ConnectivityCommandResolverInterface
{
    public const string USER_PROMPT = 'Ответь ровно ok без Markdown.';

    private const string SYSTEM_PROMPT = <<<'PROMPT'
You are a connectivity check agent. Reply exactly: ok
PROMPT;

    private const string APPEND_PROMPT = <<<'PROMPT'
Connectivity check only. Answer exactly: ok
PROMPT;

    private const string SYSTEM_MARKER = '@system-prompt';
    private const string APPEND_MARKER = '@append-system-prompt';

    #[Override]
    public function resolve(ConnectivityRoleTargetVo $target): ConnectivityResolvedCommandVo
    {
        $command = $target->getCommand();
        $cleanupPaths = [];

        $systemPromptPath = null;
        if ($this->containsMarker($command, self::SYSTEM_MARKER)) {
            $systemPromptPath = $this->createTempPromptFile('system', self::SYSTEM_PROMPT);
            $cleanupPaths[] = $systemPromptPath;
        }

        $usesCodexSemantics = $this->usesCodexSemantics($command);
        $appendPromptPath = null;
        if ($this->containsMarker($command, self::APPEND_MARKER) && !$usesCodexSemantics) {
            $appendPromptPath = $this->createTempPromptFile('append', self::APPEND_PROMPT);
            $cleanupPaths[] = $appendPromptPath;
        }

        try {
            $resolvedCommand = $this->replaceMarkers(
                command: $command,
                systemPromptPath: $systemPromptPath,
                appendPromptValue: $usesCodexSemantics ? $this->escapeTomlString(self::APPEND_PROMPT) : $appendPromptPath,
            );
            $resolvedCommand[] = self::USER_PROMPT;

            return new ConnectivityResolvedCommandVo(
                roleName: $target->getRoleName(),
                command: $resolvedCommand,
                cleanupPaths: $cleanupPaths,
            );
        } catch (\Throwable $e) {
            $this->cleanupPaths($cleanupPaths);

            throw $e;
        }
    }

    #[Override]
    public function cleanup(ConnectivityResolvedCommandVo $resolvedCommand): void
    {
        $this->cleanupPaths($resolvedCommand->getCleanupPaths());
    }

    /**
     * @param list<string> $command
     */
    private function usesCodexSemantics(array $command): bool
    {
        $executable = basename($command[0] ?? '');

        return $executable === 'codex' || str_contains($executable, 'codex');
    }

    /**
     * @param list<string> $command
     */
    private function containsMarker(array $command, string $marker): bool
    {
        foreach ($command as $argument) {
            if (str_contains($argument, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function replaceMarkers(array $command, ?string $systemPromptPath, ?string $appendPromptValue): array
    {
        $resolved = [];

        foreach ($command as $argument) {
            $value = $argument;

            if ($systemPromptPath !== null) {
                $value = str_replace(self::SYSTEM_MARKER, $systemPromptPath, $value);
            }

            if ($appendPromptValue !== null) {
                $value = str_replace(self::APPEND_MARKER, $appendPromptValue, $value);
            }

            $resolved[] = $value;
        }

        return $resolved;
    }

    private function createTempPromptFile(string $kind, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), sprintf('to-connectivity-%s-', $kind));
        if ($path === false) {
            throw new RuntimeException(sprintf('Failed to create temporary %s prompt file.', $kind));
        }

        if (file_put_contents($path, $content . PHP_EOL) === false) {
            if (is_file($path)) {
                unlink($path);
            }

            throw new RuntimeException(sprintf('Failed to write temporary %s prompt file: %s', $kind, $path));
        }

        return $path;
    }

    private function escapeTomlString(string $value): string
    {
        $escaped = str_replace('\\', '\\\\', trim($value));
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace("\n", '\\n', $escaped);
        $escaped = str_replace("\t", '\\t', $escaped);
        $escaped = str_replace("\r", '\\r', $escaped);

        return $escaped;
    }

    /**
     * @param list<string> $paths
     */
    private function cleanupPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
