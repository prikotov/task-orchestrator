<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;

/**
 * Стаб RunAgentServiceInterface для integration-тестов.
 *
 * Вместо реального AI-агента возвращает предзаданные результаты из очереди.
 * Позволяет тестировать полный путь через все слои без внешних зависимостей.
 */
final class StubRunAgentService implements RunAgentServiceInterface
{
    /** @var list<ChainRunResultVo> очередь результатов */
    private array $results = [];

    /** @var list<ChainRunRequestVo> записанные запросы */
    private array $recordedRequests = [];

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $this->recordedRequests[] = $request;

        if ($this->results === []) {
            throw new \LogicException(
                sprintf('StubRunAgentService: no more results queued for role "%s".', $request->getRole()),
            );
        }

        return array_shift($this->results);
    }

    /**
     * Добавляет успешный результат в очередь.
     */
    public function pushSuccess(
        string $outputText,
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $cost = 0.0,
    ): self {
        $this->results[] = ChainRunResultVo::createFromSuccess(
            outputText: $outputText,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
        );

        return $this;
    }

    /**
     * Добавляет ошибочный результат в очередь.
     */
    public function pushError(string $errorMessage, int $exitCode = 1): self
    {
        $this->results[] = ChainRunResultVo::createFromError(
            errorMessage: $errorMessage,
            exitCode: $exitCode,
        );

        return $this;
    }

    /**
     * Добавляет результат с таймаутом в очередь.
     */
    public function pushTimeout(string $errorMessage = 'Agent timed out'): self
    {
        $this->results[] = ChainRunResultVo::createFromError(
            errorMessage: $errorMessage,
            timedOut: true,
        );

        return $this;
    }

    /**
     * Возвращает все записанные запросы.
     *
     * @return list<ChainRunRequestVo>
     */
    public function getRecordedRequests(): array
    {
        return $this->recordedRequests;
    }

    /**
     * Возвращает последний записанный запрос или null.
     */
    public function getLastRequest(): ?ChainRunRequestVo
    {
        return $this->recordedRequests !== []
            ? $this->recordedRequests[count($this->recordedRequests) - 1]
            : null;
    }
}
