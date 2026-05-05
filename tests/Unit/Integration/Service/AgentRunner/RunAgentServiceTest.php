<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Service\AgentRunner;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner\AgentDtoMapper;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner\RunAgentService;

#[CoversClass(RunAgentService::class)]
#[CoversClass(AgentDtoMapper::class)]
#[CoversClass(RunAgentCommandHandler::class)]
final class RunAgentServiceTest extends TestCase
{
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code', runnerName: 'pi');
    }

    #[Test]
    public function runDelegatesToCommandHandlerAndMapsResult(): void
    {
        $agentResult = AgentResultVo::createFromSuccess(
            outputText: 'Code written',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        $service = $this->createService($agentResult);
        $result = $service->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Code written', $result->getOutputText());
        self::assertSame(100, $result->getInputTokens());
        self::assertSame(50, $result->getOutputTokens());
        self::assertSame(0.01, $result->getCost());
    }

    #[Test]
    public function runPassesRetryPolicyThroughMapper(): void
    {
        $retryPolicy = new ExecutionRetryPolicyVo(maxRetries: 3);
        $agentResult = AgentResultVo::createFromSuccess(outputText: 'Retried OK');

        $service = $this->createService($agentResult);
        $result = $service->run($this->request, $retryPolicy);

        self::assertFalse($result->isError());
        self::assertSame('Retried OK', $result->getOutputText());
    }

    #[Test]
    public function runReturnsErrorResultFromHandler(): void
    {
        $agentResult = AgentResultVo::createFromError(
            errorMessage: 'Timeout',
            timedOut: true,
        );

        $service = $this->createService($agentResult);
        $result = $service->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertTrue($result->isTimedOut());
    }

    /**
     * Создаёт RunAgentService с real RunAgentCommandHandler и stub-агентом.
     */
    private function createService(AgentResultVo $agentResult): RunAgentService
    {
        $runner = new StubAgentRunner($agentResult);

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('get')->with('pi')->willReturn($runner);
        $registry->method('getDefault')->willReturn($runner);

        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $retryFactory->method('createRetryableRunner')->willReturn($runner);

        $handler = new RunAgentCommandHandler($registry, $retryFactory);

        return new RunAgentService($handler, new AgentDtoMapper());
    }
}

/**
 * Stub AgentRunnerInterface: возвращает предзаданный результат.
 */
final class StubAgentRunner implements AgentRunnerInterface
{
    public function __construct(private readonly AgentResultVo $result)
    {
    }

    #[Override]
    public function getName(): string
    {
        return 'pi';
    }

    #[Override]
    public function isAvailable(): bool
    {
        return true;
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        return $this->result;
    }
}
