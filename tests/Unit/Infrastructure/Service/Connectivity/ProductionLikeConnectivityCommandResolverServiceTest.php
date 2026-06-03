<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Connectivity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityResolvedCommandVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Connectivity\ProductionLikeConnectivityCommandResolverService;

#[CoversClass(ProductionLikeConnectivityCommandResolverService::class)]
#[CoversClass(ConnectivityResolvedCommandVo::class)]
#[CoversClass(ConnectivityRoleTargetVo::class)]
final class ProductionLikeConnectivityCommandResolverServiceTest extends TestCase
{
    private ProductionLikeConnectivityCommandResolverService $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ProductionLikeConnectivityCommandResolverService();
    }

    #[Test]
    public function resolvesPiStylePlaceholdersWithTempFilesAndUserPromptArgv(): void
    {
        $resolved = $this->resolver->resolve(new ConnectivityRoleTargetVo(
            roleName: 'backend_developer_tony',
            command: ['pi', '--system-prompt', '@system-prompt', '--append-system-prompt', '@append-system-prompt'],
        ));

        try {
            $command = $resolved->getCommand();

            self::assertNotContains('@system-prompt', $command);
            self::assertNotContains('@append-system-prompt', $command);
            self::assertSame(ProductionLikeConnectivityCommandResolverService::USER_PROMPT, $command[array_key_last($command)]);

            $systemIndex = array_search('--system-prompt', $command, true);
            self::assertIsInt($systemIndex);
            $systemPromptPath = $command[$systemIndex + 1];
            self::assertFileExists($systemPromptPath);
            self::assertStringContainsString('Reply exactly: ok', (string) file_get_contents($systemPromptPath));

            $appendIndex = array_search('--append-system-prompt', $command, true);
            self::assertIsInt($appendIndex);
            $appendPromptPath = $command[$appendIndex + 1];
            self::assertFileExists($appendPromptPath);
            self::assertStringContainsString('Answer exactly: ok', (string) file_get_contents($appendPromptPath));
        } finally {
            $paths = $resolved->getCleanupPaths();
            $this->resolver->cleanup($resolved);
        }

        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    #[Test]
    public function resolvesCodexAppendPlaceholderAsInlineTomlContent(): void
    {
        $resolved = $this->resolver->resolve(new ConnectivityRoleTargetVo(
            roleName: 'system_architect_gandalf',
            command: [
                'codex',
                'exec',
                '-c',
                'model_instructions_file="@system-prompt"',
                '-c',
                'developer_instructions="@append-system-prompt"',
            ],
        ));

        try {
            $command = $resolved->getCommand();

            self::assertSame(ProductionLikeConnectivityCommandResolverService::USER_PROMPT, $command[array_key_last($command)]);
            self::assertStringNotContainsString('@system-prompt', implode(' ', $command));
            self::assertStringNotContainsString('@append-system-prompt', implode(' ', $command));

            $modelConfig = $command[3];
            self::assertMatchesRegularExpression('/^model_instructions_file="(?P<path>.+)"$/', $modelConfig);
            $systemPromptPath = substr($modelConfig, strlen('model_instructions_file="'), -1);
            self::assertFileExists($systemPromptPath);

            self::assertSame(
                'developer_instructions="Connectivity check only. Answer exactly: ok"',
                $command[5],
            );
            self::assertSame([$systemPromptPath], $resolved->getCleanupPaths());
        } finally {
            $paths = $resolved->getCleanupPaths();
            $this->resolver->cleanup($resolved);
        }

        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }
}
