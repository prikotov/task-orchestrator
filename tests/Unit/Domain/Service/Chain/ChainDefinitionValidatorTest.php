<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\PromptConfigurationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

#[CoversClass(ChainDefinitionValidatorService::class)]
final class ChainDefinitionValidatorTest extends TestCase
{
    private ChainDefinitionValidatorService $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new ChainDefinitionValidatorService();
    }

    // ─── Static chain: valid → no violations ──

    #[Test]
    public function staticChainWithValidStepsReturnsNoViolations(): void
    {
        $chain = StaticChainDefinitionVo::createFromSteps(
            name: 'implement',
            description: 'Test',
            steps: [
                ChainStepVo::createAgent(role: 'dev'),
                ChainStepVo::createQualityGate(command: 'vendor/bin/phpunit', label: 'Unit tests'),
            ],
        );

        $violations = $this->validator->validate($chain);

        self::assertSame([], $violations);
    }

    // ─── Static chain: empty steps → violation ──

    #[Test]
    public function staticChainWithEmptyStepsReturnsViolation(): void
    {
        // Создаём VO через reflection, минуя guard-проверку конструктора
        $chain = $this->createStaticChainWithEmptySteps('empty-chain');

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('empty-chain', $violations[0]->getChainName());
        self::assertSame('steps', $violations[0]->getField());
        self::assertStringContainsString('must have at least one step', $violations[0]->getMessage());
    }

    // ─── Agent step without role → violation ──

    #[Test]
    public function agentStepWithoutRoleReturnsViolation(): void
    {
        $step = $this->createAgentStepWithoutRole();

        $chain = $this->createStaticChainWithSteps('test-chain', [$step]);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('steps[0].role', $violations[0]->getField());
        self::assertStringContainsString('agent step must have a role', $violations[0]->getMessage());
    }

    // ─── Quality gate step without command → violation ──

    #[Test]
    public function qualityGateStepWithoutCommandReturnsViolation(): void
    {
        $step = $this->createQualityGateStepWithoutCommand();

        $chain = $this->createStaticChainWithSteps('test-chain', [$step]);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('steps[0].command', $violations[0]->getField());
        self::assertStringContainsString('quality_gate step must have a command', $violations[0]->getMessage());
    }

    // ─── Quality gate step without label → violation ──

    #[Test]
    public function qualityGateStepWithoutLabelReturnsViolation(): void
    {
        $step = $this->createQualityGateStepWithoutLabel();

        $chain = $this->createStaticChainWithSteps('test-chain', [$step]);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('steps[0].label', $violations[0]->getField());
        self::assertStringContainsString('quality_gate step must have a label', $violations[0]->getMessage());
    }

    // ─── Multiple invalid steps → multiple violations ──

    #[Test]
    public function multipleInvalidStepsReturnMultipleViolations(): void
    {
        $step1 = $this->createAgentStepWithoutRole();
        $step2 = $this->createQualityGateStepWithoutCommand();

        $chain = $this->createStaticChainWithSteps('multi', [$step1, $step2]);

        $violations = $this->validator->validate($chain);

        self::assertCount(2, $violations);
        self::assertSame('steps[0].role', $violations[0]->getField());
        self::assertSame('steps[1].command', $violations[1]->getField());
    }

    // ─── Fix iterations: reference to unknown step → violation ──

    #[Test]
    public function fixIterationsReferenceUnknownStepReturnsViolation(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];

        // Группа ссылается на несуществующий шаг 'step_unknown'
        $fixGroup = new FixIterationGroupVo('group1', ['step1', 'step_unknown'], 3);

        $chain = $this->createStaticChainWithStepsAndFixIterations('fix-test', $steps, [$fixGroup]);

        $violations = $this->validator->validate($chain);

        // 1 нарушение: step_unknown не найден среди шагов
        self::assertCount(1, $violations);
        self::assertSame('fix_iterations', $violations[0]->getField());
        self::assertStringContainsString('references unknown step', $violations[0]->getMessage());
        self::assertStringContainsString('step_unknown', $violations[0]->getMessage());
    }

    // ─── Fix iterations: valid references → no violation ──

    #[Test]
    public function fixIterationsWithValidReferencesReturnsNoViolations(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];

        $fixGroup = new FixIterationGroupVo('group1', ['step1', 'step2'], 3);

        $chain = StaticChainDefinitionVo::createFromSteps(
            name: 'fix-valid',
            description: 'Test',
            steps: $steps,
            fixIterations: [$fixGroup],
        );

        $violations = $this->validator->validate($chain);

        self::assertSame([], $violations);
    }

    // ─── Fix iterations: step belongs to multiple groups → violation ──

    #[Test]
    public function fixIterationStepInMultipleGroupsReturnsDuplicateViolation(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'shared'),
            ChainStepVo::createAgent(role: 'qa', name: 'qa'),
            ChainStepVo::createAgent(role: 'ops', name: 'other'),
        ];

        // Шаг 'shared' входит в две группы fix-итераций одновременно;
        // остальные шаги не пересекаются между группами.
        $groupA = new FixIterationGroupVo('groupA', ['shared', 'qa'], 3);
        $groupB = new FixIterationGroupVo('groupB', ['shared', 'other'], 3);

        $chain = $this->createStaticChainWithStepsAndFixIterations('dup-test', $steps, [$groupA, $groupB]);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('fix_iterations', $violations[0]->getField());
        // Текст дословно по дизайну §3.2: шаг, первая группа, вторая группа
        self::assertSame(
            'fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").',
            $violations[0]->getMessage(),
        );
    }

    // ─── Fix iterations: unknown step reported before duplicate for the same step ──

    #[Test]
    public function fixIterationUnknownStepDoesNotEscalateToDuplicateViolation(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'known'),
        ];

        // Неизвестный шаг 'ghost' встречается в двух группах:
        // спецификация short-circuit'ит на unknown, валидатор не должен эскалировать в duplicate.
        $groupA = new FixIterationGroupVo('groupA', ['known', 'ghost'], 3);
        $groupB = new FixIterationGroupVo('groupB', ['known', 'ghost'], 3);

        $chain = $this->createStaticChainWithStepsAndFixIterations('order-test', $steps, [$groupA, $groupB]);

        $violations = $this->validator->validate($chain);

        // 'ghost' неизвестен → 2 unknown-нарушения (по одному на группу).
        // 'known' известен и в двух группах → 1 duplicate-нарушение.
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
        // Ни одно сообщение об unknown-шаге 'ghost' не должно превратиться в duplicate-сообщение.
        $ghostDuplicate = array_filter(
            $violations,
            static fn (ChainConfigViolationVo $v): bool => str_contains($v->getMessage(), 'belongs to multiple groups')
                && str_contains($v->getMessage(), 'ghost'),
        );

        self::assertCount(2, $unknownGhost);
        self::assertCount(1, $duplicateKnown);
        self::assertCount(0, $ghostDuplicate);
    }

    // ─── Anti-divergence: spec → false ⇒ validator фиксирует нарушения ──

    /**
     * Гарантирует, что любой вход, на котором спецификация возвращает false,
     * сопровождается непустым списком violations у валидатора по полю fix_iterations.
     * Спецификация выступает oracle, валидатор — детальным репортёром.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    #[Test]
    #[DataProvider('invalidFixIterationsForSpecAndValidator')]
    public function specificationFalseImpliesValidatorHasFixIterationsViolations(array $steps, array $fixIterations): void
    {
        $specification = new FixIterationsReferenceIntegritySpecification();

        // Спецификация должна отклонить вход (oracle).
        self::assertFalse($specification->isSatisfiedBy($steps, $fixIterations));

        $chain = $this->createStaticChainWithStepsAndFixIterations('anti-divergence', $steps, $fixIterations);

        $violations = $this->validator->validate($chain);

        $fixIterationsViolations = array_filter(
            $violations,
            static fn (ChainConfigViolationVo $v): bool => $v->getField() === 'fix_iterations',
        );

        self::assertNotSame([], $fixIterationsViolations, 'Validator must report fix_iterations violations when specification is false.');
    }

    /**
     * @return array<string, array{0: list<ChainStepVo>, 1: list<FixIterationGroupVo>}>
     */
    public static function invalidFixIterationsForSpecAndValidator(): array
    {
        $namedSteps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];

        return [
            'unknown step' => [
                $namedSteps,
                [new FixIterationGroupVo('group1', ['step1', 'step_unknown'], 3)],
            ],
            'duplicate step across groups' => [
                $namedSteps,
                [
                    new FixIterationGroupVo('groupA', ['step1', 'step2'], 3),
                    new FixIterationGroupVo('groupB', ['step1', 'step2'], 3),
                ],
            ],
        ];
    }

    // ─── Dynamic chain: valid → no violations ──

    #[Test]
    public function dynamicChainWithValidConfigReturnsNoViolations(): void
    {
        $chain = DynamicChainDefinitionVo::createFromDynamic(
            name: 'brainstorm',
            description: 'Test',
            facilitator: 'analyst',
            participants: ['dev', 'qa'],
            maxRounds: 5,
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );

        $violations = $this->validator->validate($chain);

        self::assertSame([], $violations);
    }

    // ─── Dynamic chain: empty facilitator → violation ──

    #[Test]
    public function dynamicChainWithEmptyFacilitatorReturnsViolation(): void
    {
        $chain = $this->createDynamicChainWithEmptyFacilitator('dyn-test');

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('facilitator', $violations[0]->getField());
        self::assertStringContainsString('must specify a facilitator', $violations[0]->getMessage());
    }

    // ─── Dynamic chain: empty participants → violation ──

    #[Test]
    public function dynamicChainWithEmptyParticipantsReturnsViolation(): void
    {
        $chain = $this->createDynamicChainWithEmptyParticipants('dyn-test');

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('participants', $violations[0]->getField());
        self::assertStringContainsString('must have at least one participant', $violations[0]->getMessage());
    }

    // ─── Dynamic chain: maxRounds < 1 → violation ──

    #[Test]
    public function dynamicChainWithMaxRoundsZeroReturnsViolation(): void
    {
        $chain = $this->createDynamicChainWithMaxRounds('dyn-test', 0);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('max_rounds', $violations[0]->getField());
        self::assertStringContainsString('max_rounds must be >= 1', $violations[0]->getMessage());
    }

    // ─── Dynamic chain: multiple violations at once ──

    #[Test]
    public function dynamicChainWithMultipleIssuesReturnsMultipleViolations(): void
    {
        // Создаём dynamic chain с пустым facilitator, пустыми participants и maxRounds=0
        $chain = $this->createDynamicChainFullyInvalid('broken');

        $violations = $this->validator->validate($chain);

        self::assertCount(3, $violations);

        $fields = array_map(static fn(ChainConfigViolationVo $v): ?string => $v->getField(), $violations);
        self::assertContains('facilitator', $fields);
        self::assertContains('participants', $fields);
        self::assertContains('max_rounds', $fields);
    }

    // ─── Step index is zero-based in field path ──

    #[Test]
    public function stepFieldPathUsesZeroBasedIndex(): void
    {
        $validStep = ChainStepVo::createAgent(role: 'ok');
        $badStep = $this->createAgentStepWithoutRole();

        $chain = $this->createStaticChainWithSteps('indexed', [$validStep, $badStep]);

        $violations = $this->validator->validate($chain);

        self::assertCount(1, $violations);
        self::assertSame('steps[1].role', $violations[0]->getField());
    }

    // ─── Static chain: empty steps skips step and fix_iterations validation ──

    #[Test]
    public function staticChainWithEmptyStepsReturnsOnlyStepsViolation(): void
    {
        $chain = $this->createStaticChainWithEmptySteps('early-return');

        $violations = $this->validator->validate($chain);

        // Только 1 нарушение: пустые steps. Не проверяются шаги и fix_iterations.
        self::assertCount(1, $violations);
        self::assertSame('steps', $violations[0]->getField());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────

    /**
     * Создаёт StaticChainDefinitionVo с пустым списком шагов.
     * Использует reflection для обхода guard-проверки в фабричном методе.
     */
    private function createStaticChainWithEmptySteps(string $name): ChainDefinitionInterface
    {
        return $this->instantiateStaticChain($name, []);
    }

    /**
     * Создаёт StaticChainDefinitionVo с заданными шагами через reflection.
     *
     * @param list<ChainStepVo> $steps
     */
    private function createStaticChainWithSteps(string $name, array $steps): ChainDefinitionInterface
    {
        return $this->instantiateStaticChain($name, $steps);
    }

    /**
     * Создаёт StaticChainDefinitionVo с шагами и fix_iterations через reflection.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    private function createStaticChainWithStepsAndFixIterations(
        string $name,
        array $steps,
        array $fixIterations,
    ): ChainDefinitionInterface {
        return $this->instantiateStaticChain($name, $steps, $fixIterations);
    }

    /**
     * Создаёт DynamicChainDefinitionVo с пустым facilitator через reflection.
     */
    private function createDynamicChainWithEmptyFacilitator(string $name): ChainDefinitionInterface
    {
        return $this->instantiateDynamicChain($name, '', ['dev'], 5);
    }

    /**
     * Создаёт DynamicChainDefinitionVo с пустыми participants через reflection.
     */
    private function createDynamicChainWithEmptyParticipants(string $name): ChainDefinitionInterface
    {
        return $this->instantiateDynamicChain($name, 'analyst', [], 5);
    }

    /**
     * Создаёт DynamicChainDefinitionVo с maxRounds < 1 через reflection.
     */
    private function createDynamicChainWithMaxRounds(string $name, int $maxRounds): ChainDefinitionInterface
    {
        return $this->instantiateDynamicChain($name, 'analyst', ['dev'], $maxRounds);
    }

    /**
     * Создаёт DynamicChainDefinitionVo со всеми нарушениями через reflection.
     */
    private function createDynamicChainFullyInvalid(string $name): ChainDefinitionInterface
    {
        return $this->instantiateDynamicChain($name, '', [], 0);
    }

    /**
     * Создаёт ChainStepVo типа agent с пустой ролью через reflection.
     */
    private function createAgentStepWithoutRole(): ChainStepVo
    {
        $ref = new ReflectionClass(ChainStepVo::class);
        /** @var ChainStepVo $step */
        $step = $ref->newInstanceWithoutConstructor();

        $typeProp = $ref->getProperty('type');
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::agent);

        $roleProp = $ref->getProperty('role');
        $roleProp->setValue($step, '');

        return $step;
    }

    /**
     * Создаёт ChainStepVo типа quality_gate с пустой command через reflection.
     */
    private function createQualityGateStepWithoutCommand(): ChainStepVo
    {
        $ref = new ReflectionClass(ChainStepVo::class);
        /** @var ChainStepVo $step */
        $step = $ref->newInstanceWithoutConstructor();

        $typeProp = $ref->getProperty('type');
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::qualityGate);

        $commandProp = $ref->getProperty('command');
        $commandProp->setValue($step, '');

        $labelProp = $ref->getProperty('label');
        $labelProp->setValue($step, 'valid label');

        return $step;
    }

    /**
     * Создаёт ChainStepVo типа quality_gate с пустым label через reflection.
     */
    private function createQualityGateStepWithoutLabel(): ChainStepVo
    {
        $ref = new ReflectionClass(ChainStepVo::class);
        /** @var ChainStepVo $step */
        $step = $ref->newInstanceWithoutConstructor();

        $typeProp = $ref->getProperty('type');
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::qualityGate);

        $commandProp = $ref->getProperty('command');
        $commandProp->setValue($step, 'vendor/bin/phpunit');

        $labelProp = $ref->getProperty('label');
        $labelProp->setValue($step, '');

        return $step;
    }

    /**
     * Инстанцирует StaticChainDefinitionVo через reflection для создания VO в «невалидном» состоянии.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    private function instantiateStaticChain(
        string $name,
        array $steps,
        array $fixIterations = [],
    ): ChainDefinitionInterface {
        $ref = new ReflectionClass(StaticChainDefinitionVo::class);
        /** @var StaticChainDefinitionVo $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        $shared = new SharedChainDefinitionVo(
            name: $name,
            description: 'Test chain',
            type: \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum::staticType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: [],
        );

        $sharedProp = $ref->getProperty('shared');
        $sharedProp->setValue($instance, $shared);

        $stepsProp = $ref->getProperty('steps');
        $stepsProp->setValue($instance, $steps);

        $fixIterationsProp = $ref->getProperty('fixIterations');
        $fixIterationsProp->setValue($instance, $fixIterations);

        $defaultRetryPolicyProp = $ref->getProperty('defaultRetryPolicy');
        $defaultRetryPolicyProp->setValue($instance, null);

        return $instance;
    }

    /**
     * Инстанцирует DynamicChainDefinitionVo через reflection для создания VO в «невалидном» состоянии.
     *
     * @param list<string> $participants
     */
    private function instantiateDynamicChain(
        string $name,
        string $facilitator,
        array $participants,
        int $maxRounds,
    ): ChainDefinitionInterface {
        $ref = new ReflectionClass(DynamicChainDefinitionVo::class);
        /** @var DynamicChainDefinitionVo $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        $shared = new SharedChainDefinitionVo(
            name: $name,
            description: 'Test chain',
            type: \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum::dynamicType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: [],
        );

        $sharedProp = $ref->getProperty('shared');
        $sharedProp->setValue($instance, $shared);

        $facilitatorProp = $ref->getProperty('facilitator');
        $facilitatorProp->setValue($instance, $facilitator);

        $participantsProp = $ref->getProperty('participants');
        $participantsProp->setValue($instance, $participants);

        $maxRoundsProp = $ref->getProperty('maxRounds');
        $maxRoundsProp->setValue($instance, $maxRounds);

        $promptConfigProp = $ref->getProperty('promptConfiguration');
        $promptConfigProp->setValue($instance, new PromptConfigurationVo(
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'fa',
            facilitatorStartPrompt: 'fs',
            facilitatorContinuePrompt: 'fc',
            facilitatorFinalizePrompt: 'ff',
            participantAppendPrompt: 'pa',
            participantUserPrompt: 'pu',
        ));

        $defaultRetryPolicyProp = $ref->getProperty('defaultRetryPolicy');
        $defaultRetryPolicyProp->setValue($instance, null);

        return $instance;
    }
}
