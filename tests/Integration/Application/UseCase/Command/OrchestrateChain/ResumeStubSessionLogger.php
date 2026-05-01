<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo;

/**
 * Стаб ChainSessionLoggerInterface для resume-тестов.
 *
 * Расширяет базовый StubSessionLogger, добавляя поддержку задаваемого
 * ChainSessionStateVo для имитации возобновлённой сессии.
 */
final class ResumeStubSessionLogger extends StubSessionLogger
{
    private ?ChainSessionStateVo $resumedState = null;

    public function setResumedState(ChainSessionStateVo $state): self
    {
        $this->resumedState = $state;

        return $this;
    }

    #[Override]
    public function getResumedState(): ?ChainSessionStateVo
    {
        return $this->resumedState;
    }
}
