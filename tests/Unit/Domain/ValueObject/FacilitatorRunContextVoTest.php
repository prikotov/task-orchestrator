<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorRunContextVo;

#[CoversClass(FacilitatorRunContextVo::class)]
final class FacilitatorRunContextVoTest extends TestCase
{
    #[Test]
    public function storesAllFields(): void
    {
        $retryPolicy = new DynamicLoopRetryPolicyVo(maxRetries: 2);
        $vo = new FacilitatorRunContextVo(
            brainstormSystemPrompt: 'sys prompt',
            facilitatorAppendPrompt: 'append prompt',
            facilitatorStartPrompt: 'start prompt',
            facilitatorContinuePrompt: 'continue prompt',
            workingDir: '/tmp/workdir',
            facilitatorSummary: 'Round summary',
            responseFilesList: '- architect: /tmp/response.md',
            timeout: 120,
            command: ['pi', '--model', 'test'],
            retryPolicy: $retryPolicy,
            taskAddendum: 'Новая вводная',
        );

        self::assertSame('sys prompt', $vo->getBrainstormSystemPrompt());
        self::assertSame('append prompt', $vo->getFacilitatorAppendPrompt());
        self::assertSame('start prompt', $vo->getFacilitatorStartPrompt());
        self::assertSame('continue prompt', $vo->getFacilitatorContinuePrompt());
        self::assertSame('/tmp/workdir', $vo->getWorkingDir());
        self::assertSame('Round summary', $vo->getFacilitatorSummary());
        self::assertSame('- architect: /tmp/response.md', $vo->getResponseFilesList());
        self::assertSame(120, $vo->getTimeout());
        self::assertSame(['pi', '--model', 'test'], $vo->getCommand());
        self::assertSame($retryPolicy, $vo->getRetryPolicy());
        self::assertSame('Новая вводная', $vo->getTaskAddendum());
    }

    #[Test]
    public function optionalFieldsHaveBackwardCompatibleDefaults(): void
    {
        $vo = new FacilitatorRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorStartPrompt: 'start',
            facilitatorContinuePrompt: 'continue',
            workingDir: null,
            facilitatorSummary: '',
            responseFilesList: '',
            timeout: 300,
        );

        self::assertNull($vo->getWorkingDir());
        self::assertSame([], $vo->getCommand());
        self::assertNull($vo->getRetryPolicy());
        self::assertNull($vo->getTaskAddendum());
    }

    #[Test]
    public function zeroTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FacilitatorRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorStartPrompt: 'start',
            facilitatorContinuePrompt: 'continue',
            workingDir: null,
            facilitatorSummary: '',
            responseFilesList: '',
            timeout: 0,
        );
    }

    #[Test]
    public function negativeTimeoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FacilitatorRunContextVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'append',
            facilitatorStartPrompt: 'start',
            facilitatorContinuePrompt: 'continue',
            workingDir: null,
            facilitatorSummary: '',
            responseFilesList: '',
            timeout: -5,
        );
    }
}
