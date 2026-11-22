<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper;

use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\ParticipantMetricsDto;

/**
 * Агрегация round-результатов dynamic-цепочки в метрики участников (по роли).
 *
 * Группировка — по роли в порядке первого появления. Модель участника — одна
 * обнаруженная в его шагах комбинация provider/model/thinking; при разных
 * комбинациях (fallback-раннеры) — model = {@see self::MODEL_MIXED_MARKER};
 * при отсутствии данных — все три поля равны null (раннер не сообщает модель,
 * либо раунды старой сессии).
 *
 * Stateless-маппер: агрегация — техническое преобразование для отчёта,
 * без бизнес-решений и I/O.
 */
final readonly class ParticipantsMetricsMapper
{
    /**
     * Маркер колонки «Model», когда у участника разные модели в шагах
     * (fallback-раннеры или смена профиля между раундами).
     */
    public const string MODEL_MIXED_MARKER = 'mixed';
    /**
     * Агрегирует round-результаты в метрики участников.
     *
     * @return list<ParticipantMetricsDto>
     */
    public function aggregate(OrchestrateChainResultDto $result): array
    {
        if (count($result->roundResults) === 0) {
            return [];
        }

        /** @var array<string, array{speeches: int, facilitatorSteps: int, duration: float, input: int, output: int, cacheRead: int, cost: float, models: list<string>}> $buckets */
        $buckets = [];

        foreach ($result->roundResults as $round) {
            $bucket = $buckets[$round->role] ?? self::createEmptyBucket();

            $bucket['speeches'] += $round->isFacilitator ? 0 : 1;
            $bucket['facilitatorSteps'] += $round->isFacilitator ? 1 : 0;
            $bucket['duration'] += $round->duration;
            $bucket['input'] += $round->inputTokens;
            $bucket['output'] += $round->outputTokens;
            $bucket['cacheRead'] += $round->cacheReadTokens;
            $bucket['cost'] += $round->cost;

            $model = $this->modelSignature($round);
            if ($model !== null && !in_array($model, $bucket['models'], true)) {
                $bucket['models'][] = $model;
            }

            $buckets[$round->role] = $bucket;
        }

        $metrics = [];
        foreach ($buckets as $role => $bucket) {
            $metrics[] = $this->buildMetrics(
                $role,
                $bucket,
                $this->resolveModelParts($bucket['models']),
            );
        }

        return $metrics;
    }

    /**
     * @return array{speeches: int, facilitatorSteps: int, duration: float, input: int, output: int, cacheRead: int, cost: float, models: list<string>}
     */
    private static function createEmptyBucket(): array
    {
        return [
            'speeches' => 0,
            'facilitatorSteps' => 0,
            'duration' => 0.0,
            'input' => 0,
            'output' => 0,
            'cacheRead' => 0,
            'cost' => 0.0,
            'models' => [],
        ];
    }

    /**
     * Подпись модели шага: provider|model|thinking; null — модель не сообщена.
     */
    private function modelSignature(DynamicRoundResultDto $round): ?string
    {
        if ($round->model === null) {
            return null;
        }

        return implode('|', [
            $round->provider ?? '',
            $round->model,
            $round->thinking ?? '',
        ]);
    }

    /**
     * Разбирает подписи моделей в (provider, model, thinking).
     *
     * @param list<string> $signatures
     *
     * @return array{provider: string|null, model: string|null, thinking: string|null}
     */
    private function resolveModelParts(array $signatures): array
    {
        if (count($signatures) === 0) {
            return ['provider' => null, 'model' => null, 'thinking' => null];
        }

        if (count($signatures) > 1) {
            return ['provider' => null, 'model' => self::MODEL_MIXED_MARKER, 'thinking' => null];
        }

        [$provider, $model, $thinking] = explode('|', $signatures[0]);

        return [
            'provider' => $provider !== '' ? $provider : null,
            'model' => $model,
            'thinking' => $thinking !== '' ? $thinking : null,
        ];
    }

    /**
     * @param array{speeches: int, facilitatorSteps: int, duration: float, input: int, output: int, cacheRead: int, cost: float, models: list<string>} $bucket
     * @param array{provider: string|null, model: string|null, thinking: string|null} $modelParts
     */
    private function buildMetrics(string $role, array $bucket, array $modelParts): ParticipantMetricsDto
    {
        $denominator = $bucket['cacheRead'] + $bucket['input'];

        return new ParticipantMetricsDto(
            role: $role,
            model: $modelParts['model'],
            provider: $modelParts['provider'],
            thinking: $modelParts['thinking'],
            speeches: $bucket['speeches'],
            facilitatorSteps: $bucket['facilitatorSteps'],
            totalDuration: $bucket['duration'],
            inputTokens: $bucket['input'],
            outputTokens: $bucket['output'],
            cacheReadTokens: $bucket['cacheRead'],
            cacheHitRate: $denominator > 0
                ? $bucket['cacheRead'] / $denominator
                : null,
            cost: $bucket['cost'],
        );
    }
}
