<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilitatorResponseVo::class)]
final class FacilitatorResponseVoTest extends TestCase
{
    #[Test]
    public function nextRoleCreatesContinueResponse(): void
    {
        $vo = FacilitatorResponseVo::createFromNextRole('architect');

        self::assertFalse($vo->isDone());
        self::assertSame('architect', $vo->getNextRole());
        self::assertNull($vo->getSynthesis());
    }

    #[Test]
    public function nextRoleWithChallenge(): void
    {
        $vo = FacilitatorResponseVo::createFromNextRole('architect', 'Explain your reasoning');

        self::assertFalse($vo->isDone());
        self::assertSame('architect', $vo->getNextRole());
        self::assertSame('Explain your reasoning', $vo->getChallenge());
    }

    #[Test]
    public function nextRoleWithoutChallengeReturnsNull(): void
    {
        $vo = FacilitatorResponseVo::createFromNextRole('marketer');

        self::assertNull($vo->getChallenge());
    }

    #[Test]
    public function doneCreatesFinishResponse(): void
    {
        $vo = FacilitatorResponseVo::createFromDone('Summary text');

        self::assertTrue($vo->isDone());
        self::assertNull($vo->getNextRole());
        self::assertSame('Summary text', $vo->getSynthesis());
    }

    #[Test]
    public function doneChallengeIsNull(): void
    {
        $vo = FacilitatorResponseVo::createFromDone('Summary');

        self::assertNull($vo->getChallenge());
    }
}
