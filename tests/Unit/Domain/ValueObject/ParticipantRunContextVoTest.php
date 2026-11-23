<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\ParticipantRunContextVo;

#[CoversClass(ParticipantRunContextVo::class)]
final class ParticipantRunContextVoTest extends TestCase
{
    #[Test]
    public function storesAllFields(): void
    {
        $retryPolicy = new DynamicLoopRetryPolicyVo(maxRetries: 2);
        $vo = new ParticipantRunContextVo(
            brainstormSystemPrompt: 'sys prompt',
            participantAppendPrompt: 'append prompt %s',
            participantUserPrompt: 'user prompt',
            workingDir: '/tmp/workdir',
            responseFilesList: '- facilitator: /tmp/response.md',
            timeout: 120,
            command: ['pi', '--model', 'test'],
            hasPreviousResponses: false,
            challenge: 'Explain your reasoning',
            promptFile: '/tmp/role.md',
            retryPolicy: $retryPolicy,
        );

        self::assertSame('sys prompt', $vo->getBrainstormSystemPrompt());
        self::assertSame('append prompt %s', $vo->getParticipantAppendPrompt());
        self::assertSame('user prompt', $vo->getParticipantUserPrompt());
        self::assertSame('/tmp/workdir', $vo->getWorkingDir());
        self::assertSame('- facilitator: /tmp/response.md', $vo->getResponseFilesList());
        self::assertSame(120, $vo->getTimeout());
        self::assertSame(['pi', '--model', 'test'], $vo->getCommand());
        self::assertFalse($vo->hasPreviousResponses());
        self::assertSame('Explain your reasoning', $vo->getChallenge());
        self::assertSame('/tmp/role.md', $vo->getPromptFile());
        self::assertSame($retryPolicy, $vo->getRetryPolicy());
    }

    #[Test]
    public function optionalFieldsHaveBackwardCompatibleDefaults(): void
    {
        $vo = new ParticipantRunContextVo(
            brainstormSystemPrompt: 'sys',
            participantAppendPrompt: 'append %s',
            participantUserPrompt: 'user',
            workingDir: null,
            responseFilesList: '',
            timeout: 300,
        );

        self::assertNull($vo->getWorkingDir());
        self::assertSame([], $vo->getCommand());
        self::assertTrue($vo->hasPreviousResponses());
        self::assertNull($vo->getChallenge());
        self::assertNull($vo->getPromptFile());
        self::assertNull($vo->getRetryPolicy());
    }

    #[Test]
    public function zeroTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParticipantRunContextVo(
            brainstormSystemPrompt: 'sys',
            participantAppendPrompt: 'append %s',
            participantUserPrompt: 'user',
            workingDir: null,
            responseFilesList: '',
            timeout: 0,
        );
    }

    #[Test]
    public function negativeTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParticipantRunContextVo(
            brainstormSystemPrompt: 'sys',
            participantAppendPrompt: 'append %s',
            participantUserPrompt: 'user',
            workingDir: null,
            responseFilesList: '',
            timeout: -1,
        );
    }
}
