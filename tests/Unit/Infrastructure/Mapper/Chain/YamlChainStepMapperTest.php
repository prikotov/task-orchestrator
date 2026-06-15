<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Mapper\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;

#[CoversClass(YamlChainStepMapper::class)]
final class YamlChainStepMapperTest extends TestCase
{
    private const string CHAIN_NAME = 'mapper_chain';

    private YamlChainStepMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new YamlChainStepMapper(new ChainStepFactory(), new YamlRetryPolicyMapper());
    }

    #[Test]
    public function mapToChainStepsDispatchesAgentToolQualityGate(): void
    {
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [
                ['type' => 'agent', 'role' => 'developer'],
                ['type' => 'tool', 'command' => 'git status', 'label' => 'Status', 'output_key' => 'status'],
                ['type' => 'quality_gate', 'command' => 'make lint', 'label' => 'Lint'],
            ],
            null,
            false,
        );

        self::assertCount(3, $steps);
        self::assertTrue($steps[0]->isAgent());
        self::assertSame('developer', $steps[0]->getRole());
        self::assertTrue($steps[1]->isTool());
        self::assertSame('status', $steps[1]->getOutputKey());
        self::assertTrue($steps[2]->isQualityGate());
    }

    #[Test]
    public function mapToChainStepsThrowsOnMissingTypeWithCurrentMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step "type" is required in chain "mapper_chain" (expected: agent, quality_gate or tool).');

        $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['role' => 'developer']],
            null,
            false,
        );
    }

    #[Test]
    public function mapToChainStepsThrowsOnUnknownTypeWithCurrentMessage(): void
    {
        // Неизвестный тип попадает в ту же ветку (tryFrom возвращает null) — backward-compatible сообщение.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step "type" is required in chain "mapper_chain" (expected: agent, quality_gate or tool).');

        $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['type' => 'unknown', 'role' => 'developer']],
            null,
            false,
        );
    }

    #[Test]
    public function mapAgentComputesRunnerExplicitFromKeyPresence(): void
    {
        // Без ключа runner → explicit=false; с runner: pi → explicit=true.
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [
                ['type' => 'agent', 'role' => 'no_runner'],
                ['type' => 'agent', 'role' => 'with_runner', 'runner' => 'pi'],
            ],
            null,
            false,
        );

        self::assertFalse($steps[0]->hasExplicitRunner());
        self::assertSame('pi', $steps[0]->getRunner());

        self::assertTrue($steps[1]->hasExplicitRunner());
        self::assertSame('pi', $steps[1]->getRunner());
    }

    #[Test]
    public function mapAgentComputesRunnerExplicitTrueForNullRunnerKey(): void
    {
        // Backward-compatible case: ключ runner присутствует со значением null → explicit=true,
        // фактический runner='pi'.
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['type' => 'agent', 'role' => 'null_runner', 'runner' => null]],
            null,
            false,
        );

        self::assertTrue($steps[0]->hasExplicitRunner());
        self::assertSame('pi', $steps[0]->getRunner());
    }

    #[Test]
    public function mapAgentPassesStepRetryPolicyToFactory(): void
    {
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['type' => 'agent', 'role' => 'dev', 'retry_policy' => ['max_retries' => 9]]],
            null,
            false,
        );

        self::assertNotNull($steps[0]->getRetryPolicy());
        self::assertSame(9, $steps[0]->getRetryPolicy()->getMaxRetries());
    }

    #[Test]
    public function mapAgentPassesNoContextFilesPresenceAsNullOrBool(): void
    {
        // Шаг 1: ключ no_context_files отсутствует → наследует chain=true.
        // Шаг 2: явный false → перекрывает chain=true (false !== null).
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [
                ['type' => 'agent', 'role' => 'inherit'],
                ['type' => 'agent', 'role' => 'override', 'no_context_files' => false],
            ],
            null,
            true,
        );

        self::assertTrue($steps[0]->hasNoContextFiles());
        self::assertFalse($steps[1]->hasNoContextFiles());
    }

    #[Test]
    public function mapAgentTreatsNullNoContextFilesAsAbsentAndInheritsChain(): void
    {
        // Edge case (zero behavioral change vs c8f2789): no_context_files: null трактуется
        // как отсутствие ключа (null-value == absent) → наследует chain, как и в оригинале
        // (`null ?? chain`). Семантика консистентна с extractRetryPolicy.
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [
                ['type' => 'agent', 'role' => 'null_value', 'no_context_files' => null],
            ],
            null,
            true,
        );

        self::assertTrue($steps[0]->hasNoContextFiles());
    }

    #[Test]
    public function mapToolMapsOutputKeyTimeoutNameWhenProvided(): void
    {
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['type' => 'tool', 'command' => 'echo hi', 'label' => 'Say', 'output_key' => 'greeting', 'timeout_seconds' => 15, 'name' => 'say']],
            null,
            false,
        );

        self::assertSame('greeting', $steps[0]->getOutputKey());
        self::assertSame(15, $steps[0]->getTimeoutSeconds());
        self::assertSame('say', $steps[0]->getName());
    }

    #[Test]
    public function mapQualityGateMapsWhenExpression(): void
    {
        $steps = $this->mapper->mapToChainSteps(
            self::CHAIN_NAME,
            [['type' => 'quality_gate', 'command' => 'make deploy', 'label' => 'Deploy', 'when' => 'steps.review.passed == true']],
            null,
            false,
        );

        self::assertTrue($steps[0]->hasCondition());
        self::assertSame('steps.review.passed', $steps[0]->getWhen()->getPath());
        self::assertSame('review', $steps[0]->getWhen()->getReferencedStepName());
    }
}
