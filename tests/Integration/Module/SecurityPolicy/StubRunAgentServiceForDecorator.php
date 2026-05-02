<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\SecurityPolicy;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Стаб RunAgentServiceInterface для Security Policy decorator integration-тестов.
 *
 * Вместо реального AI-агента возвращает фиксированный успешный результат.
 * Позволяет проверить, что decorator пропускает безопасные запросы
 * и блокирует опасные до вызова inner service.
 */
final class StubRunAgentServiceForDecorator implements RunAgentServiceInterface
{
    private bool $called = false;

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $this->called = true;

        return ChainRunResultVo::createFromSuccess(
            outputText: 'Stub agent response',
            inputTokens: 100,
            outputTokens: 200,
            cost: 0.01,
        );
    }

    /**
     * Был ли вызван inner service (decorator пропустил запрос).
     */
    public function wasCalled(): bool
    {
        return $this->called;
    }
}
