<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;

#[CoversClass(ExecRuleIdVo::class)]
final class ExecRuleIdVoTest extends TestCase
{
    #[Test]
    public function createsFromString(): void
    {
        $id = ExecRuleIdVo::createFromString('deny-rm-rf');

        self::assertSame('deny-rm-rf', $id->getValue());
    }

    #[Test]
    public function rejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        ExecRuleIdVo::createFromString('');
    }

    #[Test]
    public function equalsReturnsTrueForSameValue(): void
    {
        $id1 = ExecRuleIdVo::createFromString('rule-1');
        $id2 = ExecRuleIdVo::createFromString('rule-1');

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentValue(): void
    {
        $id1 = ExecRuleIdVo::createFromString('rule-1');
        $id2 = ExecRuleIdVo::createFromString('rule-2');

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function toStringReturnsValue(): void
    {
        $id = ExecRuleIdVo::createFromString('my-rule');

        self::assertSame('my-rule', (string) $id);
    }
}
