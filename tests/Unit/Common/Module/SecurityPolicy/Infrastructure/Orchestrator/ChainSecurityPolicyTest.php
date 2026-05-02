<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckChainSecurityServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator\ChainSecurityPolicy;

#[CoversClass(ChainSecurityPolicy::class)]
final class ChainSecurityPolicyTest extends TestCase
{
    private SecurityPolicyServiceInterface $securityPolicyService;
    private ChainSecurityPolicy $chainSecurityPolicy;

    protected function setUp(): void
    {
        $this->securityPolicyService = $this->createMock(SecurityPolicyServiceInterface::class);
        $this->chainSecurityPolicy = new ChainSecurityPolicy($this->securityPolicyService);
    }

    // ─── checkChainExecution: delegates to SecurityPolicyServiceInterface ──

    #[Test]
    public function checkChainExecutionDelegatesToDomainService(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('code-review', 'static');

        $this->chainSecurityPolicy->checkChainExecution('code-review', ChainTypeEnum::staticType);
    }

    #[Test]
    public function checkChainExecutionConvertsEnumValueToString(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('brainstorm', 'dynamic');

        $this->chainSecurityPolicy->checkChainExecution('brainstorm', ChainTypeEnum::dynamicType);
    }

    #[Test]
    public function checkChainExecutionConvertsConditionalType(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('conditional-chain', 'conditional');

        $this->chainSecurityPolicy->checkChainExecution('conditional-chain', ChainTypeEnum::conditionalType);
    }

    #[Test]
    public function checkChainExecutionPropagatesViolationException(): void
    {
        $this->securityPolicyService
            ->method('checkChainExecution')
            ->willThrowException(new SecurityPolicyViolationException(
                'blocked-chain',
                'Chain is not allowed.',
            ));

        $this->expectException(SecurityPolicyViolationException::class);

        $this->chainSecurityPolicy->checkChainExecution('blocked-chain', ChainTypeEnum::staticType);
    }

    #[Test]
    public function checkChainExecutionCompletesSilentlyOnSuccess(): void
    {
        $this->securityPolicyService
            ->expects($this->once())
            ->method('checkChainExecution');

        $this->chainSecurityPolicy->checkChainExecution('allowed-chain', ChainTypeEnum::staticType);

        // No exception — test passes
        $this->assertTrue(true);
    }
}
