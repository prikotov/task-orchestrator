<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Service\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Service\AgentRunner\AgentDtoMapper;

#[CoversClass(AgentDtoMapper::class)]
final class AgentDtoMapperTest extends TestCase
{
    private AgentDtoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AgentDtoMapper();
    }

    // ──── mapToRunAgentCommand ──────────────────────────────────────────

    #[Test]
    public function mapToRunAgentCommandMapsAllFields(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Analyze data',
            systemPrompt: 'Be precise',
            previousContext: 'ctx-123',
            model: 'gpt-4',
            tools: 'toolset-a',
            workingDir: '/tmp/work',
            timeout: 600,
            maxContextLength: 80000,
            command: ['run', '--verbose'],
            runnerArgs: ['--append-system-prompt', '/path/to/prompt.md'],
        );

        $result = $this->mapper->mapToRunAgentCommand($vo, 'pi');

        self::assertSame('pi', $result->runnerName);
        self::assertSame('analyst', $result->role);
        self::assertSame('Analyze data', $result->task);
        self::assertSame('Be precise', $result->systemPrompt);
        self::assertSame('ctx-123', $result->previousContext);
        self::assertSame('gpt-4', $result->model);
        self::assertSame('toolset-a', $result->tools);
        self::assertSame('/tmp/work', $result->workingDir);
        self::assertSame(600, $result->timeout);
        self::assertSame(80000, $result->maxContextLength);
        self::assertSame(['run', '--verbose'], $result->command);
        self::assertSame(['--append-system-prompt', '/path/to/prompt.md'], $result->runnerArgs);
        self::assertNull($result->retryMaxRetries);
    }

    #[Test]
    public function mapToRunAgentCommandWithMinimalFields(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Fix bug');

        $result = $this->mapper->mapToRunAgentCommand($vo, 'codex');

        self::assertSame('codex', $result->runnerName);
        self::assertSame('dev', $result->role);
        self::assertSame('Fix bug', $result->task);
        self::assertNull($result->systemPrompt);
        self::assertNull($result->previousContext);
        self::assertNull($result->model);
        self::assertNull($result->tools);
        self::assertNull($result->workingDir);
        self::assertSame(300, $result->timeout);
        self::assertSame(50000, $result->maxContextLength);
        self::assertSame([], $result->command);
        self::assertSame([], $result->runnerArgs);
        self::assertNull($result->retryMaxRetries);
    }

    #[Test]
    public function mapToRunAgentCommandDefaultsRunnerNameToEmpty(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Fix bug');

        $result = $this->mapper->mapToRunAgentCommand($vo);

        self::assertSame('', $result->runnerName);
    }

    #[Test]
    public function mapToRunAgentCommandWithEnabledRetryPolicy(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Write code');
        $retryPolicy = new ChainRetryPolicyVo(
            maxRetries: 5,
            initialDelayMs: 500,
            maxDelayMs: 60000,
            multiplier: 3.0,
        );

        $result = $this->mapper->mapToRunAgentCommand($vo, 'pi', $retryPolicy);

        self::assertSame(5, $result->retryMaxRetries);
        self::assertSame(500, $result->retryInitialDelayMs);
        self::assertSame(60000, $result->retryMaxDelayMs);
        self::assertSame(3.0, $result->retryMultiplier);
    }

    #[Test]
    public function mapToRunAgentCommandWithDisabledRetryPolicy(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Write code');
        $retryPolicy = ChainRetryPolicyVo::disabled();

        $result = $this->mapper->mapToRunAgentCommand($vo, 'pi', $retryPolicy);

        self::assertNull($result->retryMaxRetries);
    }

    #[Test]
    public function mapToRunAgentCommandWithNullRetryPolicy(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Write code');

        $result = $this->mapper->mapToRunAgentCommand($vo, 'pi', null);

        self::assertNull($result->retryMaxRetries);
    }

    // ──── mapFromRunAgentResultDto (success) ───────────────────────────

    #[Test]
    public function mapFromRunAgentResultDtoMapsSuccessResult(): void
    {
        $dto = new RunAgentResultDto(
            outputText: 'Hello world',
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 10,
            cacheWriteTokens: 5,
            cost: 0.025,
            exitCode: 0,
            model: 'gpt-4',
            turns: 3,
            isError: false,
            errorMessage: null,
        );

        $result = $this->mapper->mapFromRunAgentResultDto($dto);

        self::assertInstanceOf(ChainRunResultVo::class, $result);
        self::assertFalse($result->isError());
        self::assertSame('Hello world', $result->getOutputText());
        self::assertSame(100, $result->getInputTokens());
        self::assertSame(50, $result->getOutputTokens());
        self::assertSame(10, $result->getCacheReadTokens());
        self::assertSame(5, $result->getCacheWriteTokens());
        self::assertSame(0.025, $result->getCost());
        self::assertSame('gpt-4', $result->getModel());
        self::assertSame(3, $result->getTurns());
        self::assertSame(0, $result->getExitCode());
        self::assertNull($result->getErrorMessage());
    }

    #[Test]
    public function mapFromRunAgentResultDtoMapsErrorResult(): void
    {
        $dto = new RunAgentResultDto(
            outputText: '',
            inputTokens: 0,
            outputTokens: 0,
            cacheReadTokens: 0,
            cacheWriteTokens: 0,
            cost: 0.0,
            exitCode: 429,
            model: null,
            turns: 0,
            isError: true,
            errorMessage: 'Rate limit exceeded',
        );

        $result = $this->mapper->mapFromRunAgentResultDto($dto);

        self::assertInstanceOf(ChainRunResultVo::class, $result);
        self::assertTrue($result->isError());
        self::assertSame('', $result->getOutputText());
        self::assertSame('Rate limit exceeded', $result->getErrorMessage());
        self::assertSame(429, $result->getExitCode());
        self::assertSame(0, $result->getInputTokens());
        self::assertSame(0, $result->getOutputTokens());
        self::assertSame(0.0, $result->getCost());
        self::assertNull($result->getModel());
        self::assertSame(0, $result->getTurns());
    }
}
