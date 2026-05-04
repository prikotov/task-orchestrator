<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ConditionOperatorEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionExpressionVo::class)]
#[CoversClass(ConditionOperatorEnum::class)]
final class ConditionExpressionVoTest extends TestCase
{
    // ── Parsing: valid expressions ───────────────────────────────────────────

    #[Test]
    public function parsesEqualsExpression(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');

        self::assertSame('steps.tests.passed == true', $vo->getRawExpression());
        self::assertSame('steps.tests.passed', $vo->getPath());
        self::assertSame(ConditionOperatorEnum::equals, $vo->getOperator());
        self::assertSame('true', $vo->getExpectedValue());
    }

    #[Test]
    public function parsesNotEqualsExpression(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('steps.lint.exitCode != 0');

        self::assertSame('steps.lint.exitCode != 0', $vo->getRawExpression());
        self::assertSame('steps.lint.exitCode', $vo->getPath());
        self::assertSame(ConditionOperatorEnum::notEquals, $vo->getOperator());
        self::assertSame('0', $vo->getExpectedValue());
    }

    #[Test]
    public function parsesResultStatusExpression(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('result.status == success');

        self::assertSame('result.status', $vo->getPath());
        self::assertSame(ConditionOperatorEnum::equals, $vo->getOperator());
        self::assertSame('success', $vo->getExpectedValue());
    }

    #[Test]
    public function trimsWhitespace(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('  steps.test.passed  ==  true  ');

        self::assertSame('steps.test.passed  ==  true', $vo->getRawExpression());
        self::assertSame('steps.test.passed', $vo->getPath());
        self::assertSame('true', $vo->getExpectedValue());
    }

    #[Test]
    public function parsesStringValueWithSpaces(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('result.message == hello world');

        self::assertSame('result.message', $vo->getPath());
        self::assertSame('hello world', $vo->getExpectedValue());
    }

    // ── Step reference helpers ───────────────────────────────────────────────

    #[Test]
    public function referencesStepReturnsTrueForStepPath(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');

        self::assertTrue($vo->referencesStep());
    }

    #[Test]
    public function referencesStepReturnsFalseForNonStepPath(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('result.status == success');

        self::assertFalse($vo->referencesStep());
    }

    #[Test]
    public function getReferencedStepNameReturnsName(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('steps.lint.exitCode == 0');

        self::assertSame('lint', $vo->getReferencedStepName());
    }

    #[Test]
    public function getReferencedStepNameReturnsNullForNonStepPath(): void
    {
        $vo = ConditionExpressionVo::createFromExpression('result.status == ok');

        self::assertNull($vo->getReferencedStepName());
    }

    // ── equals() ─────────────────────────────────────────────────────────────

    #[Test]
    public function equalsReturnsTrueForSameExpression(): void
    {
        $a = ConditionExpressionVo::createFromExpression('steps.test.passed == true');
        $b = ConditionExpressionVo::createFromExpression('steps.test.passed == true');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentExpression(): void
    {
        $a = ConditionExpressionVo::createFromExpression('steps.test.passed == true');
        $b = ConditionExpressionVo::createFromExpression('steps.test.passed == false');

        self::assertFalse($a->equals($b));
    }

    // ── Validation: errors ───────────────────────────────────────────────────

    #[Test]
    public function throwsOnEmptyExpression(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        ConditionExpressionVo::createFromExpression('');
    }

    #[Test]
    public function throwsOnWhitespaceOnlyExpression(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        ConditionExpressionVo::createFromExpression('   ');
    }

    #[Test]
    public function throwsOnMissingOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition expression');

        ConditionExpressionVo::createFromExpression('steps.test.passed');
    }

    #[Test]
    public function throwsOnUnsupportedOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition expression');

        ConditionExpressionVo::createFromExpression('steps.test.passed >= 1');
    }

    #[Test]
    public function throwsOnInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition path');

        ConditionExpressionVo::createFromExpression('123invalid.path == true');
    }

    #[Test]
    public function throwsOnSingleSegmentPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition path');

        ConditionExpressionVo::createFromExpression('nosteps == true');
    }

    #[Test]
    public function throwsOnEmptyPathAfterOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition expression');

        // Empty path: expression starts with operator
        ConditionExpressionVo::createFromExpression(' == true');
    }

    #[Test]
    public function throwsOnEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition expression');

        // Empty value: nothing after operator
        ConditionExpressionVo::createFromExpression('steps.test.passed ==');
    }
}
