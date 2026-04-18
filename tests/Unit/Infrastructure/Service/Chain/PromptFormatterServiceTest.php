<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\PromptFormatterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptFormatterService::class)]
final class PromptFormatterServiceTest extends TestCase
{
    private PromptFormatterService $service;

    protected function setUp(): void
    {
        $this->service = new PromptFormatterService();
    }

    // --- buildStaticContext ---

    #[Test]
    public function buildStaticContextFormatsCorrectly(): void
    {
        $result = $this->service->buildStaticContext(
            role: 'system_analyst',
            previousOutput: 'Previous analysis',
            task: 'Implement feature',
        );

        self::assertStringContainsString('system_analyst', $result);
        self::assertStringContainsString('Previous analysis', $result);
        self::assertStringContainsString('Implement feature', $result);
    }

    // --- buildFacilitatorContext ---

    #[Test]
    public function buildFacilitatorContextUsesStartPromptWhenNoSummary(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: 'Start: %s',
            continuePrompt: 'Cont: %s %s %s',
            topic: 'Design system',
            facilitatorSummary: '',
            responseFilesList: '',
        );

        self::assertSame('Start: Design system', $result);
    }

    #[Test]
    public function buildFacilitatorContextUsesContinuePromptWhenHasSummary(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: 'Start: %s',
            continuePrompt: 'Cont: %s %s %s',
            topic: 'Design system',
            facilitatorSummary: 'Summary so far',
            responseFilesList: '',
        );

        self::assertSame('Cont: Design system Summary so far ', $result);
    }

    // --- buildFinalizeContext ---

    #[Test]
    public function buildFinalizeContextFormatsCorrectly(): void
    {
        $result = $this->service->buildFinalizeContext(
            finalizePrompt: 'Final: %s %s',
            topic: 'Topic',
            responseFilesList: 'file1.md',
        );

        self::assertSame('Final: Topic file1.md', $result);
    }

    // --- buildParticipantUserPrompt ---

    #[Test]
    public function buildParticipantUserPromptWithPreviousResponsesAndChallenge(): void
    {
        $result = $this->service->buildParticipantUserPrompt(
            userPromptTemplate: 'Topic: %s Files: %s',
            topic: 'Architecture',
            responseFilesList: 'file1.md',
            hasPreviousResponses: true,
            challenge: 'Consider scaling',
        );

        self::assertStringStartsWith('Consider scaling', $result);
        self::assertStringContainsString('Architecture', $result);
        self::assertStringContainsString('file1.md', $result);
    }

    #[Test]
    public function buildParticipantUserPromptRemovesSectionWhenNoPreviousResponses(): void
    {
        $template = "Topic: %s Files: %s\n\n# Выступления предыдущих участников:\n";

        $result = $this->service->buildParticipantUserPrompt(
            userPromptTemplate: $template,
            topic: 'Architecture',
            responseFilesList: 'file.md',
            hasPreviousResponses: false,
            challenge: null,
        );

        self::assertStringNotContainsString('Выступления предыдущих участников', $result);
    }

    #[Test]
    public function buildParticipantUserPromptWithoutChallenge(): void
    {
        $result = $this->service->buildParticipantUserPrompt(
            userPromptTemplate: 'Topic: %s Files: %s',
            topic: 'Test',
            responseFilesList: '',
            hasPreviousResponses: false,
            challenge: null,
        );

        self::assertStringContainsString('Test', $result);
    }

    // --- resolveSlot ---

    #[Test]
    public function resolveSlotReplacesMarker(): void
    {
        $result = $this->service->resolveSlot(
            command: ['pi', '--mode', 'json', '{{SESSION_FILE}}'],
            marker: '{{SESSION_FILE}}',
            sessionFilePath: '/tmp/session.json',
            fallbackKey: '--session',
        );

        self::assertSame(['pi', '--mode', 'json', '/tmp/session.json'], $result);
    }

    #[Test]
    public function resolveSlotAppendsFallbackWhenMarkerNotFound(): void
    {
        $result = $this->service->resolveSlot(
            command: ['pi', '--mode', 'json'],
            marker: '{{SESSION_FILE}}',
            sessionFilePath: '/tmp/session.json',
            fallbackKey: '--session',
        );

        self::assertSame(['pi', '--mode', 'json', '--session', '/tmp/session.json'], $result);
    }

    // --- buildAgentInvocation ---

    #[Test]
    public function buildAgentInvocationWithCommand(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            command: ['pi', '--mode', 'json', '-p'],
        );

        $result = $this->service->buildAgentInvocation($request, 'user_prompt.md');

        self::assertStringContainsString('pi', $result);
        self::assertStringContainsString('--mode', $result);
        self::assertStringContainsString('user_prompt.md', $result);
    }

    #[Test]
    public function buildAgentInvocationWithEmptyCommandUsesDefault(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            command: [],
        );

        $result = $this->service->buildAgentInvocation($request, 'user_prompt.md');

        self::assertStringContainsString('pi', $result);
        self::assertStringContainsString('--mode', $result);
        self::assertStringContainsString('json', $result);
        self::assertStringContainsString('user_prompt.md', $result);
    }

    #[Test]
    public function buildAgentInvocationAddsModel(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            model: 'gpt-4o',
            command: [],
        );

        $result = $this->service->buildAgentInvocation($request, 'prompt.md');

        self::assertStringContainsString('--model', $result);
        self::assertStringContainsString('gpt-4o', $result);
    }

    #[Test]
    public function buildAgentInvocationAddsNoToolsWhenToolsEmpty(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            tools: '',
            command: [],
        );

        $result = $this->service->buildAgentInvocation($request, 'prompt.md');

        self::assertStringContainsString('--no-tools', $result);
    }

    #[Test]
    public function buildAgentInvocationAddsToolsWhenSet(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            tools: 'tool1,tool2',
            command: [],
        );

        $result = $this->service->buildAgentInvocation($request, 'prompt.md');

        self::assertStringContainsString('--tools', $result);
        self::assertStringContainsString('tool1,tool2', $result);
    }

    #[Test]
    public function buildAgentInvocationAddsWorkingDirComment(): void
    {
        $request = new ChainRunRequestVo(
            role: 'analyst',
            task: 'Test',
            workingDir: '/tmp/work',
            command: [],
        );

        $result = $this->service->buildAgentInvocation($request, 'prompt.md');

        self::assertStringContainsString('# cwd: /tmp/work', $result);
    }

    // --- buildUserPromptFileName ---

    #[Test]
    public function buildUserPromptFileNameFormatsWithPadding(): void
    {
        $result = $this->service->buildUserPromptFileName(step: 1, round: 3, role: 'architect');

        self::assertSame('step_001_round_003_architect_2_user.md', $result);
    }

    #[Test]
    public function buildUserPromptFileNameHandlesLargeNumbers(): void
    {
        $result = $this->service->buildUserPromptFileName(step: 100, round: 99, role: 'dev');

        self::assertSame('step_100_round_099_dev_2_user.md', $result);
    }
}
