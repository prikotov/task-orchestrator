<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter\AgentVoMapper;

#[CoversClass(AgentVoMapper::class)]
final class AgentVoMapperTest extends TestCase
{
    private AgentVoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AgentVoMapper();
    }

    // ──── mapToAgentRequest ─────────────────────────────────────────────

    #[Test]
    public function mapToAgentRequestMapsAllFields(): void
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

        $result = $this->mapper->mapToAgentRequest($vo);

        self::assertSame('analyst', $result->getRole());
        self::assertSame('Analyze data', $result->getTask());
        self::assertSame('Be precise', $result->getSystemPrompt());
        self::assertSame('ctx-123', $result->getPreviousContext());
        self::assertSame('gpt-4', $result->getModel());
        self::assertSame('toolset-a', $result->getTools());
        self::assertSame('/tmp/work', $result->getWorkingDir());
        self::assertSame(600, $result->getTimeout());
        self::assertSame(80000, $result->getMaxContextLength());
        self::assertSame(['run', '--verbose'], $result->getCommand());
        self::assertSame(['--append-system-prompt', '/path/to/prompt.md'], $result->getRunnerArgs());
    }

    #[Test]
    public function mapToAgentRequestWithMinimalFields(): void
    {
        $vo = new ChainRunRequestVo(role: 'dev', task: 'Fix bug');

        $result = $this->mapper->mapToAgentRequest($vo);

        self::assertSame('dev', $result->getRole());
        self::assertSame('Fix bug', $result->getTask());
        self::assertNull($result->getSystemPrompt());
        self::assertNull($result->getPreviousContext());
        self::assertNull($result->getModel());
        self::assertNull($result->getTools());
        self::assertNull($result->getWorkingDir());
        self::assertSame(300, $result->getTimeout());
        self::assertSame(50000, $result->getMaxContextLength());
        self::assertSame([], $result->getCommand());
        self::assertSame([], $result->getRunnerArgs());
    }

    // ──── mapFromAgentResult (success) ─────────────────────────────────

    #[Test]
    public function mapFromAgentResultMapsSuccessResult(): void
    {
        $agentResult = AgentResultVo::createFromSuccess(
            outputText: 'Hello world',
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 10,
            cacheWriteTokens: 5,
            cost: 0.025,
            model: 'gpt-4',
            turns: 3,
        );

        $result = $this->mapper->mapFromAgentResult($agentResult);

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
    public function mapFromAgentResultMapsErrorResult(): void
    {
        $agentResult = AgentResultVo::createFromError(
            errorMessage: 'Rate limit exceeded',
            exitCode: 429,
        );

        $result = $this->mapper->mapFromAgentResult($agentResult);

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

    // ──── mapFromAgentTurnResult ────────────────────────────────────────

    #[Test]
    public function mapFromAgentTurnResultMapsAllFields(): void
    {
        $agentResult = AgentResultVo::createFromSuccess(outputText: 'Turn output');
        $turnVo = new AgentTurnResultVo(
            agentResult: $agentResult,
            duration: 1.5,
            userPrompt: 'What is 2+2?',
            systemPrompt: 'You are a calculator',
            invocation: 'inv-001',
        );

        $result = $this->mapper->mapFromAgentTurnResult($turnVo);

        self::assertInstanceOf(ChainTurnResultVo::class, $result);
        self::assertFalse($result->agentResult->isError());
        self::assertSame('Turn output', $result->agentResult->getOutputText());
        self::assertSame(1.5, $result->duration);
        self::assertSame('What is 2+2?', $result->userPrompt);
        self::assertSame('You are a calculator', $result->systemPrompt);
        self::assertSame('inv-001', $result->invocation);
    }

    #[Test]
    public function mapFromAgentTurnResultWithDefaultOptionalFields(): void
    {
        $agentResult = AgentResultVo::createFromSuccess(outputText: 'OK');
        $turnVo = new AgentTurnResultVo(
            agentResult: $agentResult,
            duration: 0.3,
        );

        $result = $this->mapper->mapFromAgentTurnResult($turnVo);

        self::assertSame('OK', $result->agentResult->getOutputText());
        self::assertSame(0.3, $result->duration);
        self::assertSame('', $result->userPrompt);
        self::assertSame('', $result->systemPrompt);
        self::assertNull($result->invocation);
    }

    #[Test]
    public function mapFromAgentTurnResultWithErrorAgentResult(): void
    {
        $agentResult = AgentResultVo::createFromError(errorMessage: 'Timeout');
        $turnVo = new AgentTurnResultVo(
            agentResult: $agentResult,
            duration: 30.0,
            invocation: 'inv-err',
        );

        $result = $this->mapper->mapFromAgentTurnResult($turnVo);

        self::assertTrue($result->agentResult->isError());
        self::assertSame('Timeout', $result->agentResult->getErrorMessage());
        self::assertSame(30.0, $result->duration);
        self::assertSame('inv-err', $result->invocation);
    }

    // ──── mapToAgentRetryPolicy ─────────────────────────────────────────

    #[Test]
    public function mapToAgentRetryPolicyMapsEnabledPolicy(): void
    {
        $vo = new ChainRetryPolicyVo(
            maxRetries: 5,
            initialDelayMs: 500,
            maxDelayMs: 60000,
            multiplier: 3.0,
        );

        $result = $this->mapper->mapToAgentRetryPolicy($vo);

        self::assertNotNull($result);
        self::assertSame(5, $result->getMaxRetries());
        self::assertSame(500, $result->getInitialDelayMs());
        self::assertSame(60000, $result->getMaxDelayMs());
        self::assertSame(3.0, $result->getMultiplier());
    }

    #[Test]
    public function mapToAgentRetryPolicyReturnsNullForDisabledPolicy(): void
    {
        $vo = ChainRetryPolicyVo::disabled();

        $result = $this->mapper->mapToAgentRetryPolicy($vo);

        self::assertNull($result);
    }

    #[Test]
    public function mapToAgentRetryPolicyReturnsNullForNullInput(): void
    {
        $result = $this->mapper->mapToAgentRetryPolicy(null);

        self::assertNull($result);
    }
}
