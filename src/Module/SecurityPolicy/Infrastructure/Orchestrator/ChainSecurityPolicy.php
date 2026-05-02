<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckChainSecurityServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;

/**
 * Infrastructure-реализация port CheckChainSecurityServiceInterface.
 *
 * Делегирует проверку в SecurityPolicyServiceInterface (Domain Service).
 * Конвертирует ChainTypeEnum (Orchestrator Domain) → string (SecurityPolicy Domain).
 *
 * Расположение: SecurityPolicy/Infrastructure/Orchestrator/ — Dependency Inversion.
 * Port определён в Orchestrator Domain, реализация — в SecurityPolicy Infrastructure.
 *
 * @see CheckChainSecurityServiceInterface
 * @see SecurityPolicyServiceInterface::checkChainExecution()
 */
final readonly class ChainSecurityPolicy implements CheckChainSecurityServiceInterface
{
    public function __construct(
        private SecurityPolicyServiceInterface $securityPolicyService,
    ) {
    }

    #[Override]
    public function checkChainExecution(string $chainName, ChainTypeEnum $type): void
    {
        $this->securityPolicyService->checkChainExecution(
            chainName: $chainName,
            chainType: $type->value,
        );
    }
}
