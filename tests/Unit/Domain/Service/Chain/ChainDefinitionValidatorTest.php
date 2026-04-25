<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FixIterationGroupVo;

#[CoversClass(ChainDefinitionValidator::class)]
final class ChainDefinitionValidatorTest extends TestCase
{
    private ChainDefinitionValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new ChainDefinitionValidator();
    }

    // ─── Static chain: valid → no violations ──

    #[Test]
    public function staticChainWithValidStepsReturnsNoViolations(): void
    {
        $chain = ChainDefinitionVo::createFromSteps(
            name: 'implement',
            description: 'Test',
            steps: [
                ChainStepVo::agent(role: 'dev'),
                ChainStepVo::qualityGate(command: 'vendor/bin/phpunit', label: 'Unit tests'),
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
            ChainStepVo::agent(role: 'dev', name: 'step1'),
            ChainStepVo::agent(role: 'qa', name: 'step2'),
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
            ChainStepVo::agent(role: 'dev', name: 'step1'),
            ChainStepVo::agent(role: 'qa', name: 'step2'),
        ];

        $fixGroup = new FixIterationGroupVo('group1', ['step1', 'step2'], 3);

        $chain = ChainDefinitionVo::createFromSteps(
            name: 'fix-valid',
            description: 'Test',
            steps: $steps,
            fixIterations: [$fixGroup],
        );

        $violations = $this->validator->validate($chain);

        self::assertSame([], $violations);
    }

    // ─── Dynamic chain: valid → no violations ──

    #[Test]
    public function dynamicChainWithValidConfigReturnsNoViolations(): void
    {
        $chain = ChainDefinitionVo::createFromDynamic(
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
        $validStep = ChainStepVo::agent(role: 'ok');
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
     * Создаёт static ChainDefinitionVo с пустым списком шагов.
     * Использует reflection для обхода guard-проверки в фабричном методе.
     */
    private function createStaticChainWithEmptySteps(string $name): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::staticType,
            steps: [],
            fixIterations: [],
        );
    }

    /**
     * Создаёт static ChainDefinitionVo с заданными шагами через reflection.
     *
     * @param list<ChainStepVo> $steps
     */
    private function createStaticChainWithSteps(string $name, array $steps): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::staticType,
            steps: $steps,
            fixIterations: [],
        );
    }

    /**
     * Создаёт static ChainDefinitionVo с шагами и fix_iterations через reflection.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    private function createStaticChainWithStepsAndFixIterations(
        string $name,
        array $steps,
        array $fixIterations,
    ): ChainDefinitionVo {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::staticType,
            steps: $steps,
            fixIterations: $fixIterations,
        );
    }

    /**
     * Создаёт dynamic ChainDefinitionVo с пустым facilitator через reflection.
     */
    private function createDynamicChainWithEmptyFacilitator(string $name): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::dynamicType,
            steps: [],
            fixIterations: [],
            facilitator: '',
            participants: ['dev'],
            maxRounds: 5,
        );
    }

    /**
     * Создаёт dynamic ChainDefinitionVo с пустыми participants через reflection.
     */
    private function createDynamicChainWithEmptyParticipants(string $name): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::dynamicType,
            steps: [],
            fixIterations: [],
            facilitator: 'analyst',
            participants: [],
            maxRounds: 5,
        );
    }

    /**
     * Создаёт dynamic ChainDefinitionVo с maxRounds < 1 через reflection.
     */
    private function createDynamicChainWithMaxRounds(string $name, int $maxRounds): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::dynamicType,
            steps: [],
            fixIterations: [],
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: $maxRounds,
        );
    }

    /**
     * Создаёт dynamic ChainDefinitionVo со всеми нарушениями через reflection.
     */
    private function createDynamicChainFullyInvalid(string $name): ChainDefinitionVo
    {
        return $this->instantiateChainDefinition(
            name: $name,
            type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum::dynamicType,
            steps: [],
            fixIterations: [],
            facilitator: '',
            participants: [],
            maxRounds: 0,
        );
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
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum::agent);

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
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum::qualityGate);

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
        $typeProp->setValue($step, \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum::qualityGate);

        $commandProp = $ref->getProperty('command');
        $commandProp->setValue($step, 'vendor/bin/phpunit');

        $labelProp = $ref->getProperty('label');
        $labelProp->setValue($step, '');

        return $step;
    }

    /**
     * Инстанцирует ChainDefinitionVo через reflection для создания VO в «невалидном» состоянии.
     *
     * Это нужно для тестирования Validator: VO создаётся минуя guard-проверки конструктора,
     * чтобы Validator мог обнаружить нарушения.
     */
    private function instantiateChainDefinition(
        string $name,
        \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum $type,
        array $steps,
        array $fixIterations,
        ?string $facilitator = null,
        array $participants = [],
        int $maxRounds = 10,
    ): ChainDefinitionVo {
        $ref = new ReflectionClass(ChainDefinitionVo::class);
        /** @var ChainDefinitionVo $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        $nameProp = $ref->getProperty('name');
        $nameProp->setValue($instance, $name);

        $descriptionProp = $ref->getProperty('description');
        $descriptionProp->setValue($instance, 'Test chain');

        $typeProp = $ref->getProperty('type');
        $typeProp->setValue($instance, $type);

        $stepsProp = $ref->getProperty('steps');
        $stepsProp->setValue($instance, $steps);

        $fixIterationsProp = $ref->getProperty('fixIterations');
        $fixIterationsProp->setValue($instance, $fixIterations);

        $facilitatorProp = $ref->getProperty('facilitator');
        $facilitatorProp->setValue($instance, $facilitator);

        $participantsProp = $ref->getProperty('participants');
        $participantsProp->setValue($instance, $participants);

        $maxRoundsProp = $ref->getProperty('maxRounds');
        $maxRoundsProp->setValue($instance, $maxRounds);

        return $instance;
    }
}
