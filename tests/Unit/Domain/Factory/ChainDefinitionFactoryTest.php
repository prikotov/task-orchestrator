<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;

#[CoversClass(ChainDefinitionFactory::class)]
final class ChainDefinitionFactoryTest extends TestCase
{
    private ChainDefinitionFactory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification());
    }

    // ─── Happy path: создание специализированных VO ──

    #[Test]
    public function createFromStepsCreatesStaticChain(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'implement'),
            ChainStepVo::createAgent(role: 'qa', name: 'review'),
        ];
        $fixIterations = [new FixIterationGroupVo('dev-review', ['implement', 'review'], 3)];

        $chain = $this->factory->createFromSteps(
            name: 'implement',
            description: 'Implementation cycle',
            steps: $steps,
            fixIterations: $fixIterations,
        );

        self::assertInstanceOf(StaticChainDefinitionVo::class, $chain);
        self::assertSame('implement', $chain->getName());
        self::assertSame(ChainTypeEnum::staticType, $chain->getType());
        self::assertSame($steps, $chain->getSteps());
        self::assertSame($fixIterations, $chain->getFixIterations());
    }

    #[Test]
    public function createFromConditionalStepsCreatesConditionalChain(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'implement'),
            ChainStepVo::createAgent(role: 'qa', name: 'review'),
        ];

        $chain = $this->factory->createFromConditionalSteps(
            name: 'cond',
            description: 'Conditional chain',
            steps: $steps,
        );

        self::assertInstanceOf(ConditionalChainDefinitionVo::class, $chain);
        self::assertSame(ChainTypeEnum::conditionalType, $chain->getType());
    }

    #[Test]
    public function createFromDynamicCreatesDynamicChain(): void
    {
        $chain = $this->factory->createFromDynamic(
            name: 'brainstorm',
            description: 'Brainstorm session',
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

        self::assertInstanceOf(DynamicChainDefinitionVo::class, $chain);
        self::assertSame('brainstorm', $chain->getName());
        self::assertSame(ChainTypeEnum::dynamicType, $chain->getType());
        self::assertSame('analyst', $chain->getFacilitator());
        self::assertSame(['dev', 'qa'], $chain->getParticipants());
        self::assertSame(5, $chain->getMaxRounds());
    }

    // ─── Non-fix-iter guards сохраняют текущие сообщения ──

    #[Test]
    public function createFromStepsThrowsOnEmptySteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain "empty" must have at least one step');

        $this->factory->createFromSteps(
            name: 'empty',
            description: '',
            steps: [],
        );
    }

    #[Test]
    public function createFromConditionalStepsThrowsOnEmptySteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain "empty" must have at least one step');

        $this->factory->createFromConditionalSteps(
            name: 'empty',
            description: '',
            steps: [],
        );
    }

    #[Test]
    public function createFromDynamicThrowsOnEmptyFacilitator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must specify a facilitator role');

        $this->factory->createFromDynamic(
            name: 'bad',
            description: '',
            facilitator: '',
            participants: ['a'],
            maxRounds: 5,
            brainstormSystemPrompt: 'BS',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
        );
    }

    #[Test]
    public function createFromDynamicThrowsOnEmptyParticipants(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one participant');

        $this->factory->createFromDynamic(
            name: 'bad',
            description: '',
            facilitator: 'analyst',
            participants: [],
            maxRounds: 5,
            brainstormSystemPrompt: 'BS',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
        );
    }

    // ─── fix-iter guard: generic сообщение ──

    #[Test]
    public function createFromStepsThrowsGenericOnUnknownFixIterationStep(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'ghost'], 3)];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain "broken": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group');

        $this->factory->createFromSteps(
            name: 'broken',
            description: '',
            steps: $steps,
            fixIterations: $fixIterations,
        );
    }

    #[Test]
    public function createFromStepsThrowsGenericOnDuplicateFixIterationStep(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'a', name: 'shared'),
            ChainStepVo::createAgent(role: 'b', name: 'only_a'),
            ChainStepVo::createAgent(role: 'c', name: 'only_b'),
        ];
        $fixIterations = [
            new FixIterationGroupVo('group-a', ['shared', 'only_a'], 2),
            new FixIterationGroupVo('group-b', ['shared', 'only_b'], 2),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must belong to at most one fix_iteration group');

        $this->factory->createFromSteps(
            name: 'broken',
            description: '',
            steps: $steps,
            fixIterations: $fixIterations,
        );
    }

    #[Test]
    public function createFromConditionalStepsThrowsGenericOnInvalidFixIterations(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'ghost'], 3)];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain "cond-broken": fix_iterations must reference existing named steps');

        $this->factory->createFromConditionalSteps(
            name: 'cond-broken',
            description: '',
            steps: $steps,
            fixIterations: $fixIterations,
        );
    }

    // ─── Передача опциональных полей (budget, retry, timeout) ──

    #[Test]
    public function createFromStepsPropagatesOptionalFields(): void
    {
        $retryPolicy = new ChainRetryPolicyVo(maxRetries: 3, initialDelayMs: 100);
        $budget = new BudgetVo(maxCostTotal: 5.0);

        $chain = $this->factory->createFromSteps(
            name: 'rich',
            description: '',
            steps: [ChainStepVo::createAgent(role: 'r')],
            defaultRetryPolicy: $retryPolicy,
            budget: $budget,
            timeout: 600,
        );

        self::assertSame($retryPolicy, $chain->getDefaultRetryPolicy());
        self::assertSame($budget, $chain->getBudget());
        self::assertSame(600, $chain->getTimeout());
    }
}
