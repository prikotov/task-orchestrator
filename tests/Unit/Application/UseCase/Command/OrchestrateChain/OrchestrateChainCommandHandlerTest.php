<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ChainDefinition\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;

#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(OrchestrateChainCommand::class)]
final class OrchestrateChainCommandHandlerTest extends TestCase
{
    private ChainDefinitionProviderInterface $chainProvider;
    private ExecutionStrategyInterface $staticStrategy;
    private ExecutionStrategyInterface $dynamicStrategy;
    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainProvider = $this->createMock(ChainDefinitionProviderInterface::class);
        $this->staticStrategy = $this->createMock(ExecutionStrategyInterface::class);
        $this->dynamicStrategy = $this->createMock(ExecutionStrategyInterface::class);

        // Default supports(): static supports static, dynamic supports dynamic
        $this->staticStrategy->method('supports')
            ->willReturnCallback(static fn(ExecutionChainInfoVo $chainInfo): bool => $chainInfo->type === ChainExecutionTypeEnum::staticType);
        $this->dynamicStrategy->method('supports')
            ->willReturnCallback(static fn(ExecutionChainInfoVo $chainInfo): bool => $chainInfo->type === ChainExecutionTypeEnum::dynamicType);

        $this->handler = $this->createHandler();
    }

    private function createHandler(): OrchestrateChainCommandHandler
    {
        return new OrchestrateChainCommandHandler(
            $this->chainProvider,
            [$this->staticStrategy, $this->dynamicStrategy],
        );
    }

    // --- Dispatcher tests ---

    #[Test]
    public function invokeDelegatesStaticChainToStaticStrategy(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test', ChainExecutionTypeEnum::staticType);

        $this->chainProvider->method('loadChainInfo')->with('test')->willReturn($chainInfo);

        $staticResult = new OrchestrateChainResultDto();
        $this->staticStrategy->method('execute')->willReturn($staticResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Implement feature',
        ));

        self::assertSame($staticResult, $result);
    }

    #[Test]
    public function invokeDelegatesDynamicChainToDynamicStrategy(): void
    {
        $chainInfo = new ExecutionChainInfoVo('brainstorm', ChainExecutionTypeEnum::dynamicType);

        $this->chainProvider->method('loadChainInfo')->with('brainstorm')->willReturn($chainInfo);

        $dynamicResult = new OrchestrateChainResultDto(
            synthesis: 'Result',
        );
        $this->dynamicStrategy->method('execute')->willReturn($dynamicResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Design a system',
        ));

        self::assertSame($dynamicResult, $result);
        self::assertSame('Result', $result->synthesis);
    }

    #[Test]
    public function invokeCallsResumeWhenResumeDirIsSet(): void
    {
        $chainInfo = new ExecutionChainInfoVo('brainstorm', ChainExecutionTypeEnum::dynamicType);

        $this->chainProvider->method('loadChainInfo')->willReturn($chainInfo);

        $resumeResult = new OrchestrateChainResultDto(
            synthesis: 'Resumed result',
            sessionDir: '/tmp/resume-dir',
        );
        $this->dynamicStrategy->method('resume')->willReturn($resumeResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame($resumeResult, $result);
        self::assertSame('/tmp/resume-dir', $result->sessionDir);
    }

    #[Test]
    public function invokeThrowsWhenNoStrategySupportsChain(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No execution strategy found for chain "unknown".');

        $chainInfo = new ExecutionChainInfoVo('unknown', ChainExecutionTypeEnum::staticType);

        $this->chainProvider->method('loadChainInfo')->willReturn($chainInfo);

        // Both strategies return false for supports()
        $handler = new OrchestrateChainCommandHandler(
            $this->chainProvider,
            [], // empty strategies
        );

        ($handler)(new OrchestrateChainCommand(
            chainName: 'unknown',
            task: 'Test',
        ));
    }
}
