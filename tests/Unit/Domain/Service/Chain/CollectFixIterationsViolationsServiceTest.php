<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

/**
 * @see CollectFixIterationsViolationsService
 *
 * Покрывает edge-cases дизайна §7.5 и двусторонний oracle-check
 * (спецификация false ⟺ коллектор непуст; спецификация true ⟺ коллектор пуст).
 * Спецификация {@see FixIterationsReferenceIntegritySpecification} — oracle.
 */
#[CoversClass(CollectFixIterationsViolationsService::class)]
final class CollectFixIterationsViolationsServiceTest extends TestCase
{
    private CollectFixIterationsViolationsService $collector;

    #[Override]
    protected function setUp(): void
    {
        $this->collector = new CollectFixIterationsViolationsService();
    }

    #[Test]
    public function emptyFixIterationsReturnsNoViolations(): void
    {
        $steps = [ChainStepVo::createAgent(role: 'dev', name: 'step1')];

        $violations = $this->collector->collect('chain', $steps, []);

        self::assertSame([], $violations);
    }

    #[Test]
    public function validReferencesReturnNoViolations(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'step2'], 3)];

        $violations = $this->collector->collect('fix-valid', $steps, $fixIterations);

        self::assertSame([], $violations);
    }

    #[Test]
    public function unknownStepReturnsSingleViolationWithGroupAndStep(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'ghost'], 3)];

        $violations = $this->collector->collect('fix-test', $steps, $fixIterations);

        self::assertCount(1, $violations);
        $violation = $violations[0];
        self::assertInstanceOf(ChainConfigViolationVo::class, $violation);
        self::assertSame('fix-test', $violation->getChainName());
        self::assertSame('fix_iterations', $violation->getField());
        self::assertSame(
            'fix_iteration group "group1" references unknown step "ghost".',
            $violation->getMessage(),
        );
    }

    #[Test]
    public function stepInMultipleGroupsReturnsSingleViolationWithBothGroups(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'shared'),
            ChainStepVo::createAgent(role: 'qa', name: 'qa'),
            ChainStepVo::createAgent(role: 'ops', name: 'other'),
        ];
        $fixIterations = [
            new FixIterationGroupVo('groupA', ['shared', 'qa'], 3),
            new FixIterationGroupVo('groupB', ['shared', 'other'], 3),
        ];

        $violations = $this->collector->collect('dup-test', $steps, $fixIterations);

        self::assertCount(1, $violations);
        self::assertSame('fix_iterations', $violations[0]->getField());
        // Текст дословно по дизайну: шаг, первая группа, вторая группа.
        self::assertSame(
            'fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").',
            $violations[0]->getMessage(),
        );
    }

    /**
     * Unknown-шаг в двух группах → ровно 2 unknown-нарушения (по числу групп),
     * без эскалации в duplicate (поведение short-circuit спецификации).
     */
    #[Test]
    public function unknownStepInTwoGroupsDoesNotEscalateToDuplicate(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'known'),
        ];
        $fixIterations = [
            new FixIterationGroupVo('groupA', ['known', 'ghost'], 3),
            new FixIterationGroupVo('groupB', ['known', 'ghost'], 3),
        ];

        $violations = $this->collector->collect('order-test', $steps, $fixIterations);

        // 'ghost' неизвестен → 2 unknown-нарушения; 'known' в двух группах → 1 duplicate.
        $unknownGhost = array_filter(
            $violations,
            static fn (ChainConfigViolationVo $v): bool => str_contains($v->getMessage(), 'references unknown step')
                && str_contains($v->getMessage(), 'ghost'),
        );
        $duplicateKnown = array_filter(
            $violations,
            static fn (ChainConfigViolationVo $v): bool => str_contains($v->getMessage(), 'belongs to multiple groups')
                && str_contains($v->getMessage(), 'known'),
        );
        $ghostDuplicate = array_filter(
            $violations,
            static fn (ChainConfigViolationVo $v): bool => str_contains($v->getMessage(), 'belongs to multiple groups')
                && str_contains($v->getMessage(), 'ghost'),
        );

        self::assertCount(2, $unknownGhost);
        self::assertCount(1, $duplicateKnown);
        self::assertCount(0, $ghostDuplicate);
    }

    /**
     * Неназванный шаг (name: null) исключается из карты шагов → ссылка на него unknown.
     */
    #[Test]
    public function unnamedStepIsExcludedFromStepNameMap(): void
    {
        $steps = [
            // Шаг без имени — не попадает в карту именованных шагов.
            ChainStepVo::createAgent(role: 'dev'),
            ChainStepVo::createAgent(role: 'qa', name: 'named'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['named', 'ghost'], 3)];

        $violations = $this->collector->collect('unnamed-test', $steps, $fixIterations);

        self::assertCount(1, $violations);
        self::assertStringContainsString('references unknown step', $violations[0]->getMessage());
        self::assertStringContainsString('ghost', $violations[0]->getMessage());
    }

    /**
     * Mixed: unknown + duplicate в одном конфиге → все нарушения собираются.
     */
    #[Test]
    public function mixedUnknownAndDuplicateAreCollectedTogether(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'shared'),
            ChainStepVo::createAgent(role: 'qa', name: 'only_a'),
            ChainStepVo::createAgent(role: 'ops', name: 'only_b'),
        ];
        $fixIterations = [
            new FixIterationGroupVo('groupA', ['shared', 'only_a', 'missing'], 3),
            new FixIterationGroupVo('groupB', ['shared', 'only_b'], 3),
        ];

        $violations = $this->collector->collect('mixed', $steps, $fixIterations);

        // 'missing' unknown (groupA), 'shared' duplicate (groupB). Порядок обхода:
        // groupA: shared→ok, only_a→ok, missing→unknown; groupB: shared→duplicate, only_b→ok.
        self::assertCount(2, $violations);
        self::assertSame(
            'fix_iteration group "groupA" references unknown step "missing".',
            $violations[0]->getMessage(),
        );
        self::assertSame(
            'fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").',
            $violations[1]->getMessage(),
        );
    }

    /**
     * Антидивергентный oracle-check: спецификация false ⟺ коллектор непуст.
     * Гарантирует, что коллектор детектит ровно те входы, которые отклоняет спецификация.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    #[Test]
    #[DataProvider('divergenceCases')]
    public function specificationAndCollectorAgreeOnValidity(array $steps, array $fixIterations): void
    {
        $specification = new FixIterationsReferenceIntegritySpecification();
        $isSatisfied = $specification->isSatisfiedBy($steps, $fixIterations);
        $violations = $this->collector->collect('oracle', $steps, $fixIterations);

        self::assertSame(
            $isSatisfied,
            $violations === [],
            'Collector must be non-empty iff specification is not satisfied.',
        );
    }

    /**
     * @return array<string, array{0: list<ChainStepVo>, 1: list<FixIterationGroupVo>}>
     */
    public static function divergenceCases(): array
    {
        $namedSteps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];

        return [
            'empty fix_iterations (valid)' => [$namedSteps, []],
            'valid references' => [$namedSteps, [new FixIterationGroupVo('g', ['step1', 'step2'], 3)]],
            'unknown step' => [$namedSteps, [new FixIterationGroupVo('g', ['step1', 'ghost'], 3)]],
            'duplicate across groups' => [
                $namedSteps,
                [
                    new FixIterationGroupVo('a', ['step1', 'step2'], 3),
                    new FixIterationGroupVo('b', ['step1', 'step2'], 3),
                ],
            ],
        ];
    }
}
