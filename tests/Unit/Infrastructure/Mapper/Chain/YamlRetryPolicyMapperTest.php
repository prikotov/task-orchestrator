<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Mapper\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;

#[CoversClass(YamlRetryPolicyMapper::class)]
final class YamlRetryPolicyMapperTest extends TestCase
{
    private YamlRetryPolicyMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new YamlRetryPolicyMapper();
    }

    #[Test]
    public function mapNullReturnsNull(): void
    {
        self::assertNull($this->mapper->mapToChainRetryPolicy(null));
    }

    #[Test]
    public function mapEmptyArrayReturnsNull(): void
    {
        self::assertNull($this->mapper->mapToChainRetryPolicy([]));
    }

    #[Test]
    public function mapFullRetryPolicyCreatesVo(): void
    {
        $result = $this->mapper->mapToChainRetryPolicy([
            'max_retries' => 5,
            'initial_delay_ms' => 200,
            'max_delay_ms' => 10000,
            'multiplier' => 1.5,
        ]);

        self::assertInstanceOf(ChainRetryPolicyVo::class, $result);
        self::assertSame(5, $result->getMaxRetries());
        self::assertSame(200, $result->getInitialDelayMs());
        self::assertSame(10000, $result->getMaxDelayMs());
        self::assertSame(1.5, $result->getMultiplier());
    }

    #[Test]
    public function mapPartialRetryPolicyUsesExistingChainRetryPolicyVoDefaults(): void
    {
        // Только max_retries задан — остальные поля берутся из дефолтов ChainRetryPolicyVo
        // (3/1000/30000/2.0), что проверяет, что маппер делегирует сборку в VO, не дублируя дефолты.
        $result = $this->mapper->mapToChainRetryPolicy(['max_retries' => 7]);

        self::assertInstanceOf(ChainRetryPolicyVo::class, $result);
        self::assertSame(7, $result->getMaxRetries());
        self::assertSame(1000, $result->getInitialDelayMs());
        self::assertSame(30000, $result->getMaxDelayMs());
        self::assertSame(2.0, $result->getMultiplier());
    }
}
