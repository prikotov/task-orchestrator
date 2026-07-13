<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Component\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    Dto\ProcessLivenessUnknownProbeResultDto,
    ProcessLivenessProbeComponent,
    ProcessLivenessProbeLinuxProcfsComponent,
    ProcessLivenessProbeUnavailableComponent,
};

#[CoversClass(ProcessLivenessProbeComponent::class)]
#[CoversClass(ProcessLivenessProbeUnavailableComponent::class)]
final class ProcessLivenessProbeComponentTest extends TestCase
{
    #[Test]
    public function probeInjectedLinuxFamilySelectsProcfsImplementation(): void
    {
        // Arrange
        $filesystem = new ProcFilesystemFake(
            files: [
                '/proc/42/task/42/children' => '',
                '/proc/42/stat' => $this->stat(),
                '/proc/42/io' => "rchar: 10\nwchar: 20\n",
            ],
            directories: ['/proc/42/task' => ['42']],
        );
        $component = new ProcessLivenessProbeComponent(
            platformFamily: 'Linux',
            linuxProcfsProbe: new ProcessLivenessProbeLinuxProcfsComponent($filesystem),
            unavailableProbe: new ProcessLivenessProbeUnavailableComponent(),
        );

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);
        self::assertNotSame([], $filesystem->readPaths);
    }

    #[Test]
    #[DataProvider('unsupportedPlatformProvider')]
    public function probeInjectedNonLinuxFamilySelectsUnavailableImplementation(string $platformFamily): void
    {
        // Arrange
        $filesystem = new ProcFilesystemFake([]);
        $component = new ProcessLivenessProbeComponent(
            platformFamily: $platformFamily,
            linuxProcfsProbe: new ProcessLivenessProbeLinuxProcfsComponent($filesystem),
            unavailableProbe: new ProcessLivenessProbeUnavailableComponent(),
        );

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessUnknownProbeResultDto::class, $result);
        self::assertSame([], $filesystem->readPaths, 'Unsupported platforms must not attempt Linux /proc reads.');
        self::assertSame([], $filesystem->listedPaths, 'Unsupported platforms must not list Linux /proc paths.');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsupportedPlatformProvider(): iterable
    {
        yield 'Darwin' => ['Darwin'];
        yield 'Windows' => ['Windows'];
        yield 'BSD' => ['BSD'];
        yield 'Solaris' => ['Solaris'];
        yield 'Unknown' => ['Unknown'];
        yield 'unexpected value' => ['Plan9'];
    }

    private function stat(): string
    {
        $fields = ['S', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        return sprintf(
            '42 (worker) %s',
            implode(' ', [...$fields, ...array_fill(0, 37, '0')]),
        );
    }
}
