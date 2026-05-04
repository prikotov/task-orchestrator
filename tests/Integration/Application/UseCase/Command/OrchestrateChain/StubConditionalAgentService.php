<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;

/**
 * Стаб RunAgentServiceInterface (Orchestrator) для conditional integration-тестов.
 *
 * Вместо реального AI-агента возвращает предзаданный результат.
 * Позволяет тестировать ConditionalExecutionStrategy через все слои без внешних зависимостей.
 */
final class StubConditionalAgentService implements RunAgentServiceInterface
{
    /** @var list<ChainRunResultVo> очередь результатов */
    private array $results = [];

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        if ($this->results !== []) {
            return array_shift($this->results);
        }

        // Default: successful result
        return ChainRunResultVo::createFromSuccess(
            outputText: 'Default agent output',
            inputTokens: 100,
            outputTokens: 200,
            cost: 0.01,
        );
    }

    /**
     * Устанавливает единственный результат (для простых тестов).
     */
    public function setResult(ChainRunResultVo $result): self
    {
        $this->results = [$result];

        return $this;
    }

    /**
     * Добавляет результат в очередь.
     */
    public function pushResult(ChainRunResultVo $result): self
    {
        $this->results[] = $result;

        return $this;
    }
}
