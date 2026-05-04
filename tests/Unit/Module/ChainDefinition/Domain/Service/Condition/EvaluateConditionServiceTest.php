<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Domain\Service\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ConditionOperatorEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Condition\EvaluateConditionService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;

final class EvaluateConditionServiceTest extends TestCase
{
    private EvaluateConditionService $service;

    protected function setUp(): void
    {
        $this->service = new EvaluateConditionService();
    }

    // ─── steps.<name>.passed == true/false ────────────────────────────

    public function testPassedEqualsTrue(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $context = ['lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testPassedEqualsTrueWhenFalse(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $context = ['lint' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed']];

        $this->assertFalse($this->service->evaluate($expression, $context));
    }

    public function testPassedEqualsFalseWhenFalse(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed == false');
        $context = ['tests' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    // ─── steps.<name>.exitCode == <int> ───────────────────────────────

    public function testExitCodeEqualsZero(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.exitCode == 0');
        $context = ['lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testExitCodeEqualsNonZero(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.build.exitCode == 1');
        $context = ['build' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testExitCodeNotEquals(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.build.exitCode != 0');
        $context = ['build' => ['passed' => false, 'exitCode' => 2, 'status' => 'failed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testExitCodeNotEqualsWhenSame(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.build.exitCode != 0');
        $context = ['build' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertFalse($this->service->evaluate($expression, $context));
    }

    // ─── steps.<name>.status == <string> ──────────────────────────────

    public function testStatusEquals(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.status == passed');
        $context = ['lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testStatusNotEquals(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.status != error');
        $context = ['lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    // ─── Missing context / unknown step ───────────────────────────────

    public function testUnknownStepReturnsEmptyString(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.nonexistent.passed == true');

        $this->assertFalse($this->service->evaluate($expression, []));
    }

    public function testEmptyContext(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');

        $this->assertFalse($this->service->evaluate($expression, []));
    }

    public function testMissingProperty(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');
        $context = ['tests' => ['exitCode' => 0]];

        $this->assertFalse($this->service->evaluate($expression, $context));
    }

    // ─── Not-equals operator ──────────────────────────────────────────

    public function testNotEqualsTrue(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed != true');
        $context = ['tests' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testNotEqualsFalse(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed != false');
        $context = ['tests' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    // ─── Numeric comparison ───────────────────────────────────────────

    public function testNumericEquality(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.exitCode == 0');
        $context = ['tests' => ['passed' => true, 'exitCode' => 0, 'status' => 'success']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testNumericInequality(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.exitCode == 5');
        $context = ['tests' => ['passed' => false, 'exitCode' => 0, 'status' => 'failed']];

        $this->assertFalse($this->service->evaluate($expression, $context));
    }

    // ─── Case normalization ───────────────────────────────────────────

    public function testCaseInsensitiveBoolComparison(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.lint.passed == True');
        $context = ['lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed']];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    // ─── Multiple steps in context ────────────────────────────────────

    public function testMultipleStepsInContext(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');
        $context = [
            'lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed'],
            'tests' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed'],
            'build' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed'],
        ];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }

    public function testReferencingPreviousStepOnly(): void
    {
        $expression = ConditionExpressionVo::createFromExpression('steps.build.exitCode != 0');
        $context = [
            'lint' => ['passed' => true, 'exitCode' => 0, 'status' => 'passed'],
            'build' => ['passed' => false, 'exitCode' => 1, 'status' => 'failed'],
        ];

        $this->assertTrue($this->service->evaluate($expression, $context));
    }
}
