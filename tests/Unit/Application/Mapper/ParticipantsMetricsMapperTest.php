<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper\ParticipantsMetricsMapper;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

#[CoversClass(ParticipantsMetricsMapper::class)]
final class ParticipantsMetricsMapperTest extends TestCase
{
    private ParticipantsMetricsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ParticipantsMetricsMapper();
    }

    #[Test]
    public function aggregateEmptyRoundResultsReturnsEmptyList(): void
    {
        $result = new OrchestrateChainResultDto();

        self::assertSame([], $this->mapper->aggregate($result));
    }

    #[Test]
    public function aggregateGroupsByRoleInFirstAppearanceOrder(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'team_lead', isFacilitator: true, round: 1),
                self::round(role: 'backend_developer', isFacilitator: false, round: 2),
                self::round(role: 'team_lead', isFacilitator: true, round: 3),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertCount(2, $metrics);
        self::assertSame('team_lead', $metrics[0]->role);
        self::assertSame('backend_developer', $metrics[1]->role);
    }

    #[Test]
    public function aggregateCountsSpeechesAndFacilitatorStepsSeparately(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'team_lead', isFacilitator: true, round: 1),
                self::round(role: 'backend_developer', isFacilitator: false, round: 2),
                self::round(role: 'team_lead', isFacilitator: true, round: 3),
                self::round(role: 'backend_developer', isFacilitator: false, round: 4),
                self::round(role: 'backend_developer', isFacilitator: false, round: 5),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertSame(2, $metrics[0]->facilitatorSteps);
        self::assertSame(0, $metrics[0]->speeches);
        self::assertSame(3, $metrics[1]->speeches);
        self::assertSame(0, $metrics[1]->facilitatorSteps);
    }

    #[Test]
    public function aggregateSumsDurationTokensCostAndComputesCacheHitRate(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(
                    role: 'backend_developer',
                    isFacilitator: false,
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.01,
                    duration: 10.0,
                    model: 'glm-5.3-flash',
                    provider: 'zai',
                    thinking: 'high',
                    cacheReadTokens: 3000,
                ),
                self::round(
                    role: 'backend_developer',
                    isFacilitator: false,
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.02,
                    duration: 5.0,
                    model: 'glm-5.3-flash',
                    provider: 'zai',
                    thinking: 'high',
                    cacheReadTokens: 1000,
                ),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertCount(1, $metrics);
        $participant = $metrics[0];
        self::assertSame(15.0, $participant->totalDuration);
        self::assertSame(2000, $participant->inputTokens);
        self::assertSame(1000, $participant->outputTokens);
        self::assertSame(4000, $participant->cacheReadTokens);
        self::assertSame(0.03, $participant->cost);
        // cache_read / (cache_read + input) = 4000 / 6000
        self::assertEqualsWithDelta(4000 / 6000, $participant->cacheHitRate ?? -1.0, 1e-9);
        self::assertSame('glm-5.3-flash', $participant->model);
        self::assertSame('zai', $participant->provider);
        self::assertSame('high', $participant->thinking);
    }

    #[Test]
    public function aggregateCacheHitRateIsNullWhenNoTokensAtAll(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'team_lead', isFacilitator: true),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertNull($metrics[0]->cacheHitRate);
    }

    #[Test]
    public function aggregateRoundsWithoutModelYieldNullModelFields(): void
    {
        // Раунды старых сессий (или раннеры без model) — «н/д» в отчёте.
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'backend_developer', isFacilitator: false, round: 1),
                self::round(role: 'backend_developer', isFacilitator: false, round: 2),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertNull($metrics[0]->model);
        self::assertNull($metrics[0]->provider);
        self::assertNull($metrics[0]->thinking);
    }

    #[Test]
    public function aggregateMixedModelsYieldMixedModelMarker(): void
    {
        // Значение публичного контракта (маркер в колонке «Model») зафиксировано.
        self::assertSame('mixed', ParticipantsMetricsMapper::MODEL_MIXED_MARKER);

        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'team_lead', isFacilitator: true, round: 1, model: 'glm-5.3-flash', provider: 'zai'),
                self::round(role: 'team_lead', isFacilitator: true, round: 2, model: 'mimo-v2.6-pro', provider: 'xiaomi-token-plan-sgp'),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertSame(ParticipantsMetricsMapper::MODEL_MIXED_MARKER, $metrics[0]->model);
        self::assertNull($metrics[0]->provider);
        self::assertNull($metrics[0]->thinking);
    }

    #[Test]
    public function aggregateModelWithoutProviderKeepsModelOnly(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::round(role: 'team_lead', isFacilitator: true, round: 1, model: 'solo-model'),
            ],
        );

        $metrics = $this->mapper->aggregate($result);

        self::assertSame('solo-model', $metrics[0]->model);
        self::assertNull($metrics[0]->provider);
    }

    private static function round(
        string $role,
        bool $isFacilitator,
        int $round = 1,
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $cost = 0.0,
        float $duration = 0.0,
        ?string $model = null,
        ?string $provider = null,
        ?string $thinking = null,
        int $cacheReadTokens = 0,
    ): DynamicRoundResultDto {
        return new DynamicRoundResultDto(
            round: $round,
            role: $role,
            isFacilitator: $isFacilitator,
            outputText: 'output',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            duration: $duration,
            model: $model,
            provider: $provider,
            thinking: $thinking,
            cacheReadTokens: $cacheReadTokens,
        );
    }
}
