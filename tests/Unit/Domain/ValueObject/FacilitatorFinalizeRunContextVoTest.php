<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorFinalizeRunContextVo;

#[CoversClass(FacilitatorFinalizeRunContextVo::class)]
final class FacilitatorFinalizeRunContextVoTest extends TestCase
{
    #[Test]
    public function storesAllFields(): void
    {
        $retryPolicy = new DynamicLoopRetryPolicyVo(maxRetries: 2);
        $vo = new FacilitatorFinalizeRunContextVo(
            brainstormSystemPrompt: 'sys prompt',
            facilitatorAppendPrompt: 'append prompt',
            facilitatorFinalizePrompt: 'finalize prompt',
            workingDir: '/tmp/workdir',
            responseFilesList: '- architect: /tmp/response.md',
            timeout: 120,
            command: ['pi', '--model', 'test'],
            retryPolicy: $retryPolicy,
        );

        self::assertSame('sys prompt', $vo->getBrainstormSystemPrompt());
        self::assertSame('append prompt', $vo->getFacilitatorAppendPrompt());
        self::assertSame('finalize prompt', $vo->getFacilitatorFinalizePrompt());
        self::assertSame('/tmp/workdir', $vo->getWorkingDir());
        self::assertSame('- architect: /tmp/response.md', $vo->getResponseFilesList());
        self::assertSame(120, $vo->getTimeout());
        self::assertSame(['pi', '--model', 'test'], $vo->getCommand());
        self::assertSame($retryPolicy, $vo->getRetryPolicy());
    }

    #[Test]
    public function optionalFieldsHaveBackwardCompatibleDefaults(): void
    {
        $vo = new FacilitatorFinalizeRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorFinalizePrompt: 'finalize',
            workingDir: null,
            responseFilesList: '',
            timeout: 300,
        );

        self::assertNull($vo->getWorkingDir());
        self::assertSame([], $vo->getCommand());
        self::assertNull($vo->getRetryPolicy());
    }

    #[Test]
    public function zeroTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FacilitatorFinalizeRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorFinalizePrompt: 'finalize',
            workingDir: null,
            responseFilesList: '',
            timeout: 0,
        );
    }

    #[Test]
    public function negativeTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FacilitatorFinalizeRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorFinalizePrompt: 'finalize',
            workingDir: null,
            responseFilesList: '',
            timeout: -10,
        );
    }
}
