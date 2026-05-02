<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckExecPolicyServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator\SecurityPolicyRunAgentDecorator;

#[CoversClass(SecurityPolicyRunAgentDecorator::class)]
final class SecurityPolicyRunAgentDecoratorTest extends TestCase
{
    private RunAgentServiceInterface $decoratedService;
    private CheckExecPolicyServiceInterface $execPolicy;
    private SecurityPolicyRunAgentDecorator $decorator;

    protected function setUp(): void
    {
        $this->decoratedService = $this->createMock(RunAgentServiceInterface::class);
        $this->execPolicy = $this->createMock(CheckExecPolicyServiceInterface::class);
        $this->decorator = new SecurityPolicyRunAgentDecorator(
            decoratedService: $this->decoratedService,
            execPolicy: $this->execPolicy,
        );
    }

    // ─── run: security check before delegation ────────────────────────

    #[Test]
    public function runChecksExecPolicyBeforeDelegation(): void
    {
        $request = new ChainRunRequestVo(
            role: 'reviewer',
            task: 'review the code',
            runnerName: 'openai',
            tools: 'read,grep',
        );
        $expectedResult = ChainRunResultVo::createFromSuccess('OK');

        // Security check is called FIRST
        $this->execPolicy
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('openai', 'review the code', 'read,grep');

        // Then decorated service is called
        $this->decoratedService
            ->expects($this->once())
            ->method('run')
            ->willReturn($expectedResult);

        $result = $this->decorator->run($request);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function runPassesRetryPolicyToDecoratedService(): void
    {
        $request = new ChainRunRequestVo(role: 'dev', task: 'test');
        $retryPolicy = new ChainRetryPolicyVo(maxRetries: 3);
        $expectedResult = ChainRunResultVo::createFromSuccess('done');

        $this->execPolicy->method('checkRunnerCommand');
        $this->decoratedService
            ->expects($this->once())
            ->method('run')
            ->with($request, $retryPolicy)
            ->willReturn($expectedResult);

        $result = $this->decorator->run($request, $retryPolicy);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function runUsesDefaultRunnerNameWhenNull(): void
    {
        $request = new ChainRunRequestVo(
            role: 'dev',
            task: 'test',
            runnerName: null, // null runner name
        );
        $expectedResult = ChainRunResultVo::createFromSuccess('ok');

        // Security check uses 'default' when runnerName is null
        $this->execPolicy
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('default', 'test', null);

        $this->decoratedService->method('run')->willReturn($expectedResult);

        $result = $this->decorator->run($request);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function runDoesNotCallDecoratedServiceOnViolation(): void
    {
        $request = new ChainRunRequestVo(
            role: 'dev',
            task: 'bash -c rm -rf /',
            runnerName: 'local-shell',
        );

        // Security check throws violation
        $this->execPolicy
            ->method('checkRunnerCommand')
            ->willThrowException(ExecPolicyViolationException::createFromRule(
                ruleId: 'deny-bash-c',
                target: \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum::command,
                pattern: 'bash -c*',
                violatedValue: 'bash -c rm -rf /',
            ));

        // Decorated service MUST NOT be called
        $this->decoratedService
            ->expects($this->never())
            ->method('run');

        $this->expectException(ExecPolicyViolationException::class);

        $this->decorator->run($request);
    }

    #[Test]
    public function runReturnsDecoratedServiceResult(): void
    {
        $request = new ChainRunRequestVo(role: 'dev', task: 'safe task');
        $expectedResult = ChainRunResultVo::createFromError('timeout', exitCode: 124, timedOut: true);

        $this->execPolicy->method('checkRunnerCommand');
        $this->decoratedService->method('run')->willReturn($expectedResult);

        $result = $this->decorator->run($request);

        $this->assertTrue($result->isError());
        $this->assertSame('timeout', $result->getErrorMessage());
        $this->assertTrue($result->isTimedOut());
    }
}
