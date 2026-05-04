<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\RoleNotFoundException;

/**
 * Маппит Domain-исключения и результаты оркестрации в типизированные exit codes.
 *
 * Application-сервис: зависит от Domain-исключений (Application → Domain — разрешено).
 * Presentation-слой делегирует маппинг сюда, не импортируя Domain-типы напрямую.
 */
final readonly class ResolveExitCodeService implements ResolveExitCodeServiceInterface
{
    #[Override]
    public function resolveFromThrowable(\Throwable $throwable): OrchestrateExitCodeEnum
    {
        return match (true) {
            $throwable instanceof ChainNotFoundException => OrchestrateExitCodeEnum::chainNotFound,
            $throwable instanceof RoleNotFoundException => OrchestrateExitCodeEnum::invalidConfig,
            default => OrchestrateExitCodeEnum::chainFailed,
        };
    }

    #[Override]
    public function resolveFromResult(OrchestrateChainResultDto $result, bool $isDynamic): OrchestrateExitCodeEnum
    {
        if ($result->budgetExceeded) {
            return OrchestrateExitCodeEnum::budgetExceeded;
        }

        if ($result->timedOut) {
            return OrchestrateExitCodeEnum::timeout;
        }

        if ($isDynamic) {
            return $result->synthesis !== null
                ? OrchestrateExitCodeEnum::success
                : OrchestrateExitCodeEnum::chainFailed;
        }

        return $this->staticChainHasError($result)
            ? OrchestrateExitCodeEnum::chainFailed
            : OrchestrateExitCodeEnum::success;
    }

    #[Override]
    public function isSuccessfulResult(OrchestrateChainResultDto $result, bool $isDynamic): bool
    {
        return $this->resolveFromResult($result, $isDynamic) === OrchestrateExitCodeEnum::success;
    }

    /**
     * Проверяет, содержит ли static-цепочка ошибку на каком-либо шаге.
     */
    private function staticChainHasError(OrchestrateChainResultDto $result): bool
    {
        foreach ($result->stepResults as $stepResult) {
            if ($stepResult->isError) {
                return true;
            }
        }

        return false;
    }
}
