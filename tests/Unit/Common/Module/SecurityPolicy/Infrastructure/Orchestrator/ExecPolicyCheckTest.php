<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator\ExecPolicyCheck;

#[CoversClass(ExecPolicyCheck::class)]
final class ExecPolicyCheckTest extends TestCase
{
    private SecurityPolicyServiceInterface $securityPolicyService;
    private ExecPolicyCheck $execPolicyCheck;

    protected function setUp(): void
    {
        $this->securityPolicyService = $this->createMock(SecurityPolicyServiceInterface::class);
        $this->execPolicyCheck = new ExecPolicyCheck($this->securityPolicyService);
    }

    // ─── checkRunnerCommand ─────────────────────────────────────────────

    #[Test]
    public function checkRunnerCommandDelegatesToDomainService(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('openai', 'review code', 'read,grep');

        $this->execPolicyCheck->checkRunnerCommand('openai', 'review code', 'read,grep');
    }

    #[Test]
    public function checkRunnerCommandPassesNullTools(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('openai', 'review code', null);

        $this->execPolicyCheck->checkRunnerCommand('openai', 'review code', null);
    }

    #[Test]
    public function checkRunnerCommandPropagatesViolationException(): void
    {
        $this->securityPolicyService
            ->method('checkRunnerCommand')
            ->willThrowException(ExecPolicyViolationException::createFromRule(
                ruleId: 'deny-bash-c',
                target: \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum::command,
                pattern: 'bash -c*',
                violatedValue: 'bash -c rm -rf /',
            ));

        $this->expectException(ExecPolicyViolationException::class);

        $this->execPolicyCheck->checkRunnerCommand('local-shell', 'bash -c rm -rf /');
    }

    #[Test]
    public function checkRunnerCommandCompletesSilentlyOnSuccess(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkRunnerCommand');

        $this->execPolicyCheck->checkRunnerCommand('openai', 'safe task');

        $this->assertTrue(true);
    }

    // ─── checkShellCommand ──────────────────────────────────────────────

    #[Test]
    public function checkShellCommandDelegatesToDomainService(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkShellCommand')
            ->with('rm -rf /');

        $this->execPolicyCheck->checkShellCommand('rm -rf /');
    }

    #[Test]
    public function checkShellCommandPropagatesViolationException(): void
    {
        $this->securityPolicyService
            ->method('checkShellCommand')
            ->willThrowException(ExecPolicyViolationException::createFromRule(
                ruleId: 'deny-rm-rf',
                target: \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum::command,
                pattern: 'rm -rf /*',
                violatedValue: 'rm -rf /',
            ));

        $this->expectException(ExecPolicyViolationException::class);

        $this->execPolicyCheck->checkShellCommand('rm -rf /');
    }

    #[Test]
    public function checkShellCommandCompletesSilentlyOnSuccess(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkShellCommand');

        $this->execPolicyCheck->checkShellCommand('ls -la');

        $this->assertTrue(true);
    }
}
