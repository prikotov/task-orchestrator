<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;

/**
 * Стаб DynamicLoopSessionLoggerInterface для resume-тестов.
 *
 * Расширяет базовый StubSessionLogger, добавляя поддержку задаваемого
 * DynamicLoopSessionStateVo для имитации возобновлённой сессии.
 */
final class ResumeStubSessionLogger extends StubSessionLogger
{
    private ?DynamicLoopSessionStateVo $resumedState = null;

    public function setResumedState(DynamicLoopSessionStateVo $state): self
    {
        $this->resumedState = $state;

        return $this;
    }

    #[Override]
    public function getResumedState(): ?DynamicLoopSessionStateVo
    {
        return $this->resumedState;
    }
}
