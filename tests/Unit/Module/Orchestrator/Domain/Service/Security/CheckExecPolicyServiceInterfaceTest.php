<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Orchestrator\Domain\Service\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckExecPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Unit-тест: контракт CheckExecPolicyServiceInterface.
 *
 * Проверяет, что mock-реализация интерфейса корректно:
 * - пропускает разрешённые runner-команды и shell-команды (void return)
 * - выбрасывает ExecPolicyViolationException для запрещённых
 * - поддерживает nullable $tools параметр
 */
final class CheckExecPolicyServiceInterfaceTest extends TestCase
{
    private CheckExecPolicyServiceInterface $service;

    protected function setUp(): void
    {
        $this->service = $this->createMock(CheckExecPolicyServiceInterface::class);
    }

    // ─── checkRunnerCommand: успешная проверка ────────────────────────

    #[Test]
    public function checkRunnerCommandSucceedsForAllowedRunner(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('openai', 'Review code for bugs', 'read,write');

        $this->service->checkRunnerCommand('openai', 'Review code for bugs', 'read,write');

        $this->assertTrue(true);
    }

    #[Test]
    public function checkRunnerCommandSucceedsWithoutTools(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('anthropic', 'Analyze code', null);

        $this->service->checkRunnerCommand('anthropic', 'Analyze code', null);

        $this->assertTrue(true);
    }

    #[Test]
    public function checkRunnerCommandSucceedsWithNullableToolsDefault(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkRunnerCommand')
            ->with('openai', 'Simple task');

        $this->service->checkRunnerCommand('openai', 'Simple task');

        $this->assertTrue(true);
    }

    // ─── checkRunnerCommand: нарушение policy ──────────────────────────

    #[Test]
    public function checkRunnerCommandThrowsForDeniedRunner(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            'rule-001',
            RuleTargetEnum::runner,
            'local-shell',
            'local-shell',
        );

        $this->service
            ->method('checkRunnerCommand')
            ->willThrowException($exception);

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('rule "rule-001" denies runner');

        $this->service->checkRunnerCommand('local-shell', 'rm -rf /');
    }

    #[Test]
    public function checkRunnerCommandViolationContainsRuleDetails(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            'rule-bash',
            RuleTargetEnum::command,
            'bash -c *',
            'bash -c "malicious"',
        );

        $this->service
            ->method('checkRunnerCommand')
            ->willThrowException($exception);

        try {
            $this->service->checkRunnerCommand('openai', 'bash -c "malicious"');
            $this->fail('Expected ExecPolicyViolationException was not thrown');
        } catch (ExecPolicyViolationException $e) {
            $this->assertSame('rule-bash', $e->getRuleId());
            $this->assertSame(RuleTargetEnum::command, $e->getTarget());
            $this->assertSame('bash -c *', $e->getPattern());
            $this->assertSame('bash -c "malicious"', $e->getViolatedValue());
        }
    }

    // ─── checkShellCommand: успешная проверка ──────────────────────────

    #[Test]
    public function checkShellCommandSucceedsForAllowedCommand(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkShellCommand')
            ->with('php vendor/bin/phpunit');

        $this->service->checkShellCommand('php vendor/bin/phpunit');

        $this->assertTrue(true);
    }

    #[Test]
    public function checkShellCommandSucceedsForQualityGateCommand(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkShellCommand')
            ->with('make lint');

        $this->service->checkShellCommand('make lint');

        $this->assertTrue(true);
    }

    // ─── checkShellCommand: нарушение policy ───────────────────────────

    #[Test]
    public function checkShellCommandThrowsForDeniedCommand(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            'rule-rm',
            RuleTargetEnum::command,
            'rm -rf *',
            'rm -rf /',
        );

        $this->service
            ->method('checkShellCommand')
            ->willThrowException($exception);

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('rule "rule-rm" denies command');

        $this->service->checkShellCommand('rm -rf /');
    }

    #[Test]
    public function checkShellCommandViolationContainsRuleDetails(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            'rule-sudo',
            RuleTargetEnum::command,
            '/usr/bin/sudo *',
            '/usr/bin/sudo rm -rf /',
        );

        $this->service
            ->method('checkShellCommand')
            ->willThrowException($exception);

        try {
            $this->service->checkShellCommand('/usr/bin/sudo rm -rf /');
            $this->fail('Expected ExecPolicyViolationException was not thrown');
        } catch (ExecPolicyViolationException $e) {
            $this->assertSame('rule-sudo', $e->getRuleId());
            $this->assertSame(RuleTargetEnum::command, $e->getTarget());
            $this->assertSame('/usr/bin/sudo *', $e->getPattern());
        }
    }

    // ─── Контракт: оба метода на одном интерфейсе ─────────────────────

    #[Test]
    public function interfaceSupportsBothCheckMethods(): void
    {
        $this->service
            ->expects($this->exactly(2))
            ->method('checkRunnerCommand')
            ->withAnyParameters();
        $this->service
            ->expects($this->exactly(2))
            ->method('checkShellCommand')
            ->withAnyParameters();

        $this->service->checkRunnerCommand('openai', 'task1');
        $this->service->checkShellCommand('echo hello');
        $this->service->checkRunnerCommand('anthropic', 'task2', 'tool1');
        $this->service->checkShellCommand('make test');

        $this->assertTrue(true);
    }
}
