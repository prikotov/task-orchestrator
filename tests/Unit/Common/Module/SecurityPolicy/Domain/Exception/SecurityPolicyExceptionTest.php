<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;

#[CoversClass(SecurityPolicyException::class)]
#[CoversClass(SecurityPolicyViolationException::class)]
#[CoversClass(ExecPolicyViolationException::class)]
final class SecurityPolicyExceptionTest extends TestCase
{
    #[Test]
    public function securityPolicyViolationExtendsBase(): void
    {
        $exception = new SecurityPolicyViolationException('test-chain', 'not allowed');

        self::assertInstanceOf(SecurityPolicyException::class, $exception);
        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('test-chain', $exception->getMessage());
        self::assertStringContainsString('not allowed', $exception->getMessage());
    }

    #[Test]
    public function securityPolicyViolationSupportsPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new SecurityPolicyViolationException('chain', 'denied', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function execPolicyViolationExtendsBase(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            ruleId: 'deny-rm',
            target: RuleTargetEnum::command,
            pattern: 'rm *',
            violatedValue: 'rm -rf /',
        );

        self::assertInstanceOf(SecurityPolicyException::class, $exception);
        self::assertStringContainsString('deny-rm', $exception->getMessage());
        self::assertStringContainsString('rm -rf /', $exception->getMessage());
        self::assertStringContainsString('rm *', $exception->getMessage());
    }

    #[Test]
    public function execPolicyViolationContainsRuleInfo(): void
    {
        $exception = ExecPolicyViolationException::createFromRule(
            ruleId: 'deny-bash',
            target: RuleTargetEnum::command,
            pattern: 'bash*',
            violatedValue: 'bash -c echo',
        );

        self::assertSame('deny-bash', $exception->getRuleId());
        self::assertSame('bash*', $exception->getPattern());
        self::assertSame(RuleTargetEnum::command, $exception->getTarget());
        self::assertSame('bash -c echo', $exception->getViolatedValue());
    }

    #[Test]
    public function execPolicyViolationDefaultConstructor(): void
    {
        $exception = new ExecPolicyViolationException('Custom message');

        self::assertNull($exception->getRuleId());
        self::assertNull($exception->getPattern());
        self::assertNull($exception->getTarget());
        self::assertNull($exception->getViolatedValue());
        self::assertSame('Custom message', $exception->getMessage());
    }

    #[Test]
    public function execPolicyViolationSupportsPrevious(): void
    {
        $previous = new \RuntimeException('root');
        $exception = ExecPolicyViolationException::createFromRule(
            ruleId: 'r1',
            target: RuleTargetEnum::runner,
            pattern: 'local-shell',
            violatedValue: 'local-shell',
            previous: $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }
}
