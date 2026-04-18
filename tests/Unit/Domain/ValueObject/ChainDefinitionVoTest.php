<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainDefinitionVo::class)]
#[CoversClass(ChainStepVo::class)]
#[CoversClass(ChainTypeEnum::class)]
#[CoversClass(RoleConfigVo::class)]
#[CoversClass(RetryPolicyVo::class)]
final class ChainDefinitionVoTest extends TestCase
{
    #[Test]
    public function createFromStepsCreatesStaticVo(): void
    {
        $steps = [
            ChainStepVo::agent(role: 'system_analyst', runner: 'pi'),
            ChainStepVo::agent(role: 'backend_developer'),
        ];

        $vo = ChainDefinitionVo::createFromSteps(
            name: 'test',
            description: 'Test chain',
            steps: $steps,
            fixIterations: [],
        );

        self::assertSame('test', $vo->getName());
        self::assertSame('Test chain', $vo->getDescription());
        self::assertSame(ChainTypeEnum::staticType, $vo->getType());
        self::assertSame([], $vo->getFixIterations());
        self::assertFalse($vo->isDynamic());
    }

    #[Test]
    public function createFromDynamicCreatesDynamicVo(): void
    {
        $vo = ChainDefinitionVo::createFromDynamic(
            name: 'brainstorm',
            description: 'Brainstorm session',
            facilitator: 'system_analyst',
            participants: ['architect', 'marketer', 'backend_developer'],
            maxRounds: 10,
            brainstormSystemPrompt: 'Base sys',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Start %s',
            facilitatorContinuePrompt: 'Cont %s %s',
            facilitatorFinalizePrompt: 'Final %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Ctx %s %s',
        );

        self::assertSame('brainstorm', $vo->getName());
        self::assertSame(ChainTypeEnum::dynamicType, $vo->getType());
        self::assertTrue($vo->isDynamic());
        self::assertSame('system_analyst', $vo->getFacilitator());
        self::assertSame(['architect', 'marketer', 'backend_developer'], $vo->getParticipants());
        self::assertSame(10, $vo->getMaxRounds());
        self::assertEmpty($vo->getSteps());
    }

    #[Test]
    public function createFromStepsThrowsOnEmptySteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must have at least one step');

        ChainDefinitionVo::createFromSteps(
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

        ChainDefinitionVo::createFromDynamic(
            name: 'bad',
            description: '',
            facilitator: '',
            participants: ['a'],
            maxRounds: 10,
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

        ChainDefinitionVo::createFromDynamic(
            name: 'bad',
            description: '',
            facilitator: 'system_analyst',
            participants: [],
            maxRounds: 10,
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
    public function createFromDynamicThrowsOnEmptyPrompts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty prompts');

        ChainDefinitionVo::createFromDynamic(
            name: 'bad',
            description: '',
            facilitator: 'system_analyst',
            participants: ['a'],
            maxRounds: 10,
            brainstormSystemPrompt: ' ',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
        );
    }

    #[Test]
    public function createFromStepsWithRoles(): void
    {
        $roles = [
            'system_analyst' => new RoleConfigVo(
                command: ['pi', '--model', 'gpt-4o-mini'],
                timeout: 600,
            ),
        ];

        $vo = ChainDefinitionVo::createFromSteps(
            name: 'test',
            description: 'Test',
            steps: [ChainStepVo::agent(role: 'system_analyst')],
            roles: $roles,
        );

        $config = $vo->getRoleConfig('system_analyst');
        self::assertNotNull($config);
        self::assertSame(['pi', '--model', 'gpt-4o-mini'], $config->getCommand());
        self::assertSame(600, $config->getTimeout());
        self::assertNull($vo->getRoleConfig('nonexistent'));
    }

    #[Test]
    public function createFromDynamicWithRoles(): void
    {
        $roles = [
            'facilitator_role' => new RoleConfigVo(command: ['--tools', 'read']),
            'participant_role' => new RoleConfigVo(command: ['--model', 'gpt-4o']),
        ];

        $vo = ChainDefinitionVo::createFromDynamic(
            name: 'dyn',
            description: '',
            facilitator: 'facilitator_role',
            participants: ['participant_role'],
            maxRounds: 5,
            brainstormSystemPrompt: 'BS',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
            roles: $roles,
        );

        self::assertSame($roles, $vo->getRoles());
        self::assertSame(['--tools', 'read'], $vo->getRoleConfig('facilitator_role')->getCommand());
    }

    #[Test]
    public function getRolesReturnsEmptyArrayByDefault(): void
    {
        $vo = ChainDefinitionVo::createFromSteps(
            name: 'test',
            description: '',
            steps: [ChainStepVo::agent(role: 'r')],
        );

        self::assertSame([], $vo->getRoles());
    }

    #[Test]
    public function createFromStepsWithDefaultRetryPolicy(): void
    {
        $retryPolicy = new RetryPolicyVo(maxRetries: 5, initialDelayMs: 500);

        $vo = ChainDefinitionVo::createFromSteps(
            name: 'retry_chain',
            description: 'Chain with retry',
            steps: [ChainStepVo::agent(role: 'r')],
            defaultRetryPolicy: $retryPolicy,
        );

        self::assertNotNull($vo->getDefaultRetryPolicy());
        self::assertSame(5, $vo->getDefaultRetryPolicy()->getMaxRetries());
        self::assertSame(500, $vo->getDefaultRetryPolicy()->getInitialDelayMs());
    }

    #[Test]
    public function createFromStepsWithoutRetryPolicyReturnsNull(): void
    {
        $vo = ChainDefinitionVo::createFromSteps(
            name: 'no_retry',
            description: '',
            steps: [ChainStepVo::agent(role: 'r')],
        );

        self::assertNull($vo->getDefaultRetryPolicy());
    }

    #[Test]
    public function createFromDynamicWithDefaultRetryPolicy(): void
    {
        $retryPolicy = new RetryPolicyVo(maxRetries: 2, initialDelayMs: 200);

        $vo = ChainDefinitionVo::createFromDynamic(
            name: 'dyn_retry',
            description: '',
            facilitator: 'fac',
            participants: ['part1'],
            maxRounds: 5,
            brainstormSystemPrompt: 'BS',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
            defaultRetryPolicy: $retryPolicy,
        );

        self::assertNotNull($vo->getDefaultRetryPolicy());
        self::assertSame(2, $vo->getDefaultRetryPolicy()->getMaxRetries());
        self::assertSame(200, $vo->getDefaultRetryPolicy()->getInitialDelayMs());
    }

    #[Test]
    public function createFromDynamicWithoutRetryPolicyReturnsNull(): void
    {
        $vo = ChainDefinitionVo::createFromDynamic(
            name: 'dyn_no_retry',
            description: '',
            facilitator: 'fac',
            participants: ['part1'],
            maxRounds: 5,
            brainstormSystemPrompt: 'BS',
            facilitatorAppendPrompt: 'FA %s',
            facilitatorStartPrompt: 'St %s',
            facilitatorContinuePrompt: 'C %s %s',
            facilitatorFinalizePrompt: 'F %s %s',
            participantAppendPrompt: 'PA %s',
            participantUserPrompt: 'P %s %s',
        );

        self::assertNull($vo->getDefaultRetryPolicy());
    }

    #[Test]
    public function chainStepVoHoldsRetryPolicy(): void
    {
        $stepPolicy = new RetryPolicyVo(maxRetries: 1);

        $step = ChainStepVo::agent(
            role: 'retry_role',
            retryPolicy: $stepPolicy,
        );

        self::assertNotNull($step->getRetryPolicy());
        self::assertSame(1, $step->getRetryPolicy()->getMaxRetries());
    }

    #[Test]
    public function chainStepVoWithoutRetryPolicyReturnsNull(): void
    {
        $step = ChainStepVo::agent(role: 'plain_role');

        self::assertNull($step->getRetryPolicy());
    }
}
