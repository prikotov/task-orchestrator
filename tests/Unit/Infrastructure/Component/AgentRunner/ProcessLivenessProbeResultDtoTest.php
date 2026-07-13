<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Component\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\{
    ProcessLivenessActiveProbeResultDto,
    ProcessLivenessInactiveProbeResultDto,
    ProcessLivenessPidSnapshotDto,
    ProcessLivenessSnapshotDto,
    ProcessLivenessUnknownProbeResultDto,
};

#[CoversClass(ProcessLivenessActiveProbeResultDto::class)]
#[CoversClass(ProcessLivenessInactiveProbeResultDto::class)]
#[CoversClass(ProcessLivenessUnknownProbeResultDto::class)]
final class ProcessLivenessProbeResultDtoTest extends TestCase
{
    #[Test]
    public function comparableResultsStructurallyRequireSnapshot(): void
    {
        // Arrange
        $snapshot = $this->snapshot();

        // Act
        $active = new ProcessLivenessActiveProbeResultDto($snapshot);
        $inactive = new ProcessLivenessInactiveProbeResultDto($snapshot);

        // Assert
        self::assertSame($snapshot, $active->snapshot);
        self::assertSame($snapshot, $inactive->snapshot);
    }

    #[Test]
    public function unknownResultStructurallyHasNoSnapshot(): void
    {
        // Act
        $unknown = new ProcessLivenessUnknownProbeResultDto();

        // Assert
        self::assertFalse(property_exists($unknown, 'snapshot'));
    }

    private function snapshot(): ProcessLivenessSnapshotDto
    {
        return new ProcessLivenessSnapshotDto([
            42 => new ProcessLivenessPidSnapshotDto(42, 1_000, 10, 20),
        ]);
    }
}
