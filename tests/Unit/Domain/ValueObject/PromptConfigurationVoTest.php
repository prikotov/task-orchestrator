<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\PromptConfigurationVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptConfigurationVo::class)]
final class PromptConfigurationVoTest extends TestCase
{
    #[Test]
    public function createWithAllPrompts(): void
    {
        $vo = new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );

        self::assertSame('System prompt', $vo->getBrainstormSystemPrompt());
        self::assertSame('Fac append %s', $vo->getFacilitatorAppendPrompt());
        self::assertSame('Fac start %s', $vo->getFacilitatorStartPrompt());
        self::assertSame('Fac continue %s %s %s', $vo->getFacilitatorContinuePrompt());
        self::assertSame('Fac finalize %s %s', $vo->getFacilitatorFinalizePrompt());
        self::assertSame('Part append %s', $vo->getParticipantAppendPrompt());
        self::assertSame('Part user %s %s', $vo->getParticipantUserPrompt());
    }

    #[Test]
    public function throwsOnEmptyBrainstormSystemPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: ' ',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyFacilitatorAppendPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: '',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyFacilitatorStartPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: '  ',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyFacilitatorContinuePrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: '',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyFacilitatorFinalizePrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: ' ',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyParticipantAppendPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: '',
            participantUserPrompt: 'Part user %s %s',
        );
    }

    #[Test]
    public function throwsOnEmptyParticipantUserPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All prompt fields must be non-empty.');

        new PromptConfigurationVo(
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: '',
        );
    }
}
