<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Orchestrator\Domain\Service\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckChainSecurityServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;

/**
 * Unit-тест: контракт CheckChainSecurityServiceInterface.
 *
 * Проверяет, что mock-реализация интерфейса корректно:
 * - пропускает разрешённые цепочки (void return)
 * - выбрасывает SecurityPolicyViolationException для запрещённых
 */
final class CheckChainSecurityServiceInterfaceTest extends TestCase
{
    private CheckChainSecurityServiceInterface $service;

    protected function setUp(): void
    {
        $this->service = $this->createMock(CheckChainSecurityServiceInterface::class);
    }

    // ─── Успешная проверка (цепочка разрешена) ───────────────────────

    #[Test]
    public function checkChainExecutionSucceedsForAllowedChain(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('code-review', ChainTypeEnum::staticType);

        $this->service->checkChainExecution('code-review', ChainTypeEnum::staticType);

        // Если не бросил exception — цепочка авторизована (void return)
        $this->assertTrue(true); // explicit assertion for test readability
    }

    #[Test]
    public function checkChainExecutionSucceedsForDynamicChain(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('brainstorm', ChainTypeEnum::dynamicType);

        $this->service->checkChainExecution('brainstorm', ChainTypeEnum::dynamicType);

        $this->assertTrue(true);
    }

    #[Test]
    public function checkChainExecutionSucceedsForConditionalChain(): void
    {
        $this->service
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('deploy', ChainTypeEnum::conditionalType);

        $this->service->checkChainExecution('deploy', ChainTypeEnum::conditionalType);

        $this->assertTrue(true);
    }

    // ─── Нарушение policy (цепочка запрещена) ─────────────────────────

    #[Test]
    public function checkChainExecutionThrowsForDeniedChain(): void
    {
        $exception = new SecurityPolicyViolationException(
            'dangerous-chain',
            'Chain is not authorized in current environment',
        );

        $this->service
            ->method('checkChainExecution')
            ->willThrowException($exception);

        $this->expectException(SecurityPolicyViolationException::class);
        $this->expectExceptionMessage('Security policy violation for chain "dangerous-chain"');

        $this->service->checkChainExecution('dangerous-chain', ChainTypeEnum::staticType);
    }

    #[Test]
    public function checkChainExecutionExceptionContainsChainName(): void
    {
        $chainName = 'restricted-pipeline';
        $exception = new SecurityPolicyViolationException(
            $chainName,
            'Not allowed in production',
        );

        $this->service
            ->method('checkChainExecution')
            ->willThrowException($exception);

        try {
            $this->service->checkChainExecution($chainName, ChainTypeEnum::dynamicType);
            $this->fail('Expected SecurityPolicyViolationException was not thrown');
        } catch (SecurityPolicyViolationException $e) {
            $this->assertStringContainsString($chainName, $e->getMessage());
        }
    }

    // ─── Контракт метода — signature ──────────────────────────────────

    #[Test]
    public function interfaceMethodAcceptsChainTypeEnum(): void
    {
        $invokedTypes = [];

        $this->service
            ->expects($this->exactly(3))
            ->method('checkChainExecution')
            ->willReturnCallback(function (string $chainName, ChainTypeEnum $type) use (&$invokedTypes): void {
                $invokedTypes[] = $type;
            });

        $this->service->checkChainExecution('s1', ChainTypeEnum::staticType);
        $this->service->checkChainExecution('d1', ChainTypeEnum::dynamicType);
        $this->service->checkChainExecution('c1', ChainTypeEnum::conditionalType);

        $this->assertCount(3, $invokedTypes);
        $this->assertSame(ChainTypeEnum::staticType, $invokedTypes[0]);
        $this->assertSame(ChainTypeEnum::dynamicType, $invokedTypes[1]);
        $this->assertSame(ChainTypeEnum::conditionalType, $invokedTypes[2]);
    }
}
