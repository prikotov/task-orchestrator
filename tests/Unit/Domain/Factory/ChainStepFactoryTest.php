<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;

#[CoversClass(ChainStepFactory::class)]
final class ChainStepFactoryTest extends TestCase
{
    private const string CHAIN_NAME = 'test_chain';

    private ChainStepFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ChainStepFactory();
    }

    // ─── Agent: runner defaults & explicit flag ──

    #[Test]
    public function createAgentUsesDefaultRunnerPiWhenRunnerMissing(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
        );

        self::assertSame(ChainStepVo::DEFAULT_RUNNER, $step->getRunner());
        self::assertFalse($step->hasExplicitRunner());
    }

    #[Test]
    public function createAgentPreservesExplicitPiRunner(): void
    {
        // runner: pi задан явно → фактический runner 'pi', но explicit=true
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: ChainStepVo::DEFAULT_RUNNER,
            runnerExplicit: true,
        );

        self::assertSame(ChainStepVo::DEFAULT_RUNNER, $step->getRunner());
        self::assertTrue($step->hasExplicitRunner());
    }

    #[Test]
    public function createAgentPreservesExplicitNonDefaultRunner(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: 'codex',
            runnerExplicit: true,
        );

        self::assertSame('codex', $step->getRunner());
        self::assertTrue($step->hasExplicitRunner());
    }

    #[Test]
    public function createAgentPreservesExplicitNullRunnerAsPiAndExplicit(): void
    {
        // Backward-compatible case: ключ runner присутствует со значением null.
        // Семантика сохранена: runner='pi' (default), но explicit=true.
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: true,
        );

        self::assertSame(ChainStepVo::DEFAULT_RUNNER, $step->getRunner());
        self::assertTrue($step->hasExplicitRunner());
    }

    #[Test]
    public function createAgentRequiresRoleWithCurrentMessage(): void
    {
        // Intentional improvement vs c8f2789 (Пуаро, REMARK 3): для role='' фабрика бросает
        // сообщение с именем цепочки, тогда как оригинал передавал '' в VO-конструктор
        // (сообщение 'Agent step must have a role.' без имени цепочки). Соответствует
        // factory.md — фабрика централизует guard-инварианты. Для role=null/absent
        // сообщение идентично оригиналу.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent step "role" is required in chain "test_chain".');

        $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: '',
            runner: null,
            runnerExplicit: false,
        );
    }

    // ─── Agent: retry_policy inheritance (step ?? chain) ──

    #[Test]
    public function createAgentInheritsChainRetryPolicyWhenStepPolicyMissing(): void
    {
        $chainPolicy = ChainRetryPolicyVo::createFromArray(['max_retries' => 5]);

        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            chainRetryPolicy: $chainPolicy,
        );

        self::assertNotNull($step->getRetryPolicy());
        self::assertSame(5, $step->getRetryPolicy()->getMaxRetries());
    }

    #[Test]
    public function createAgentUsesStepRetryPolicyOverChainPolicy(): void
    {
        $chainPolicy = ChainRetryPolicyVo::createFromArray(['max_retries' => 5]);
        $stepPolicy = ChainRetryPolicyVo::createFromArray(['max_retries' => 9]);

        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            stepRetryPolicy: $stepPolicy,
            chainRetryPolicy: $chainPolicy,
        );

        self::assertSame(9, $step->getRetryPolicy()->getMaxRetries());
    }

    #[Test]
    public function createAgentLeavesRetryPolicyNullWhenBothMissing(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
        );

        self::assertNull($step->getRetryPolicy());
    }

    // ─── Agent: no_context_files inheritance (false !== null) ──

    #[Test]
    public function createAgentInheritsNoContextFilesFromChainWhenStepMissing(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            stepNoContextFiles: null,
            chainNoContextFiles: true,
        );

        self::assertTrue($step->hasNoContextFiles());
    }

    #[Test]
    public function createAgentStepNoContextFilesFalseOverridesChainTrue(): void
    {
        // Критичный case: false на шаге перекрывает true на цепочке.
        // false !== null — отличие явного false от отсутствия ключа.
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            stepNoContextFiles: false,
            chainNoContextFiles: true,
        );

        self::assertFalse($step->hasNoContextFiles());
    }

    #[Test]
    public function createAgentStepNoContextFilesTrueOverridesChainFalse(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            stepNoContextFiles: true,
            chainNoContextFiles: false,
        );

        self::assertTrue($step->hasNoContextFiles());
    }

    #[Test]
    public function createAgentDefaultsNoContextFilesFalseWhenBothAbsent(): void
    {
        $step = $this->factory->createAgent(
            chainName: self::CHAIN_NAME,
            role: 'developer',
            runner: null,
            runnerExplicit: false,
            stepNoContextFiles: null,
            chainNoContextFiles: false,
        );

        self::assertFalse($step->hasNoContextFiles());
    }

    // ─── Tool: timeout defaults & guards ──

    #[Test]
    public function createToolUsesDefaultTimeout120WhenMissing(): void
    {
        $step = $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
        );

        self::assertSame(ChainStepVo::DEFAULT_TIMEOUT_SECONDS, $step->getTimeoutSeconds());
        self::assertTrue($step->isTool());
    }

    #[Test]
    public function createToolUsesExplicitTimeout(): void
    {
        $step = $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
            timeoutSeconds: 30,
        );

        self::assertSame(30, $step->getTimeoutSeconds());
    }

    #[Test]
    public function createToolRequiresCommandWithCurrentMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have "command" in chain "test_chain".');

        $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: null,
            label: 'Some label',
        );
    }

    #[Test]
    public function createToolRequiresLabelWithCurrentMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have "label" in chain "test_chain".');

        $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: null,
        );
    }

    #[Test]
    public function createToolPreservesOutputKeyNameWhenProvided(): void
    {
        $step = $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
            outputKey: 'commit_hash',
            name: 'hash_step',
        );

        self::assertSame('commit_hash', $step->getOutputKey());
        self::assertSame('hash_step', $step->getName());
    }

    #[Test]
    public function createToolLeavesOutputKeyNullWhenAbsent(): void
    {
        $step = $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
        );

        self::assertNull($step->getOutputKey());
        self::assertNull($step->getName());
    }

    #[Test]
    public function createToolIgnoresEmptyWhenAndEmptyPostStep(): void
    {
        $step = $this->factory->createTool(
            chainName: self::CHAIN_NAME,
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
            when: null,
            postStep: null,
        );

        self::assertNull($step->getWhen());
        self::assertFalse($step->hasCondition());
        self::assertNull($step->getPostStep());
        self::assertFalse($step->hasPostStep());
    }

    // ─── QualityGate: timeout defaults & guards ──

    #[Test]
    public function createQualityGateUsesDefaultTimeout120WhenMissing(): void
    {
        $step = $this->factory->createQualityGate(
            chainName: self::CHAIN_NAME,
            command: 'make lint',
            label: 'Lint',
        );

        self::assertSame(ChainStepVo::DEFAULT_TIMEOUT_SECONDS, $step->getTimeoutSeconds());
        self::assertTrue($step->isQualityGate());
    }

    #[Test]
    public function createQualityGateUsesExplicitTimeout(): void
    {
        $step = $this->factory->createQualityGate(
            chainName: self::CHAIN_NAME,
            command: 'make lint',
            label: 'Lint',
            timeoutSeconds: 60,
        );

        self::assertSame(60, $step->getTimeoutSeconds());
    }

    #[Test]
    public function createQualityGateRequiresCommandWithCurrentMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quality_gate step must have "command" in chain "test_chain".');

        $this->factory->createQualityGate(
            chainName: self::CHAIN_NAME,
            command: null,
            label: 'Lint',
        );
    }

    #[Test]
    public function createQualityGateRequiresLabelWithCurrentMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quality_gate step must have "label" in chain "test_chain".');

        $this->factory->createQualityGate(
            chainName: self::CHAIN_NAME,
            command: 'make lint',
            label: null,
        );
    }
}
