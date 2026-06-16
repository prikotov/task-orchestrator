<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Specification\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

#[CoversClass(FixIterationsReferenceIntegritySpecification::class)]
final class FixIterationsReferenceIntegritySpecificationTest extends TestCase
{
    private FixIterationsReferenceIntegritySpecification $specification;

    #[Override]
    protected function setUp(): void
    {
        $this->specification = new FixIterationsReferenceIntegritySpecification();
    }

    #[Test]
    public function emptyFixIterationsIsSatisfied(): void
    {
        $steps = [ChainStepVo::createAgent(role: 'dev', name: 'step1')];

        self::assertTrue($this->specification->isSatisfiedBy($steps, []));
    }

    #[Test]
    public function validReferencesAreSatisfied(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'implement'),
            ChainStepVo::createAgent(role: 'qa', name: 'review'),
        ];

        $fixIterations = [
            new FixIterationGroupVo('dev-review', ['implement', 'review'], 3),
        ];

        self::assertTrue($this->specification->isSatisfiedBy($steps, $fixIterations));
    }

    #[Test]
    public function multipleDistinctGroupsAreSatisfied(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'a', name: 's1'),
            ChainStepVo::createAgent(role: 'b', name: 's2'),
            ChainStepVo::createAgent(role: 'c', name: 's3'),
            ChainStepVo::createAgent(role: 'd', name: 's4'),
        ];

        $fixIterations = [
            new FixIterationGroupVo('group-a', ['s1', 's2'], 2),
            new FixIterationGroupVo('group-b', ['s3', 's4'], 2),
        ];

        self::assertTrue($this->specification->isSatisfiedBy($steps, $fixIterations));
    }

    #[Test]
    public function unknownStepIsNotSatisfied(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];

        $fixIterations = [
            new FixIterationGroupVo('group1', ['step1', 'step_unknown'], 3),
        ];

        self::assertFalse($this->specification->isSatisfiedBy($steps, $fixIterations));
    }

    #[Test]
    public function unnamedStepIsNotConsideredAValidReference(): void
    {
        // Шаг без имени не должен резолвить ссылку (имя null исключается из nameMap)
        $steps = [
            ChainStepVo::createAgent(role: 'dev'),
            ChainStepVo::createAgent(role: 'qa'),
        ];

        $fixIterations = [
            new FixIterationGroupVo('group1', ['step1', 'step2'], 3),
        ];

        self::assertFalse($this->specification->isSatisfiedBy($steps, $fixIterations));
    }

    #[Test]
    public function duplicateStepAcrossGroupsIsNotSatisfied(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'a', name: 'shared'),
            ChainStepVo::createAgent(role: 'b', name: 'only_a'),
            ChainStepVo::createAgent(role: 'c', name: 'only_b'),
        ];

        // 'shared' принадлежит обеим группам — нарушение инварианта
        $fixIterations = [
            new FixIterationGroupVo('group-a', ['shared', 'only_a'], 2),
            new FixIterationGroupVo('group-b', ['shared', 'only_b'], 2),
        ];

        self::assertFalse($this->specification->isSatisfiedBy($steps, $fixIterations));
    }

    #[Test]
    public function unknownStepReportedBeforeDuplicateCheck(): void
    {
        // Если первая группа ссылается на неизвестный шаг — возвращаем false сразу,
        // не доходя до проверки дубликатов.
        $steps = [ChainStepVo::createAgent(role: 'a', name: 's1')];

        $fixIterations = [
            new FixIterationGroupVo('group1', ['s1', 'ghost'], 2),
        ];

        self::assertFalse($this->specification->isSatisfiedBy($steps, $fixIterations));
    }
}
