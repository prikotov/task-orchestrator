<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Component\AgentRunner;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcFilesystemDirectoryEntriesDto,
    ProcFilesystemComponent,
    ProcFilesystemComponentInterface,
};

#[CoversClass(ProcFilesystemComponent::class)]
#[CoversClass(ProcFilesystemDirectoryEntriesDto::class)]
final class ProcFilesystemComponentIntegrationTest extends KernelTestCase
{
    private ProcFilesystemComponentInterface $component;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        $component = self::getContainer()->get(ProcFilesystemComponentInterface::class);
        self::assertInstanceOf(ProcFilesystemComponent::class, $component);
        $this->component = $component;
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Test]
    public function readExistingEmptyFilePreservesValidEmptyContents(): void
    {
        // Arrange
        $path = tempnam(sys_get_temp_dir(), 'procfs_component_');
        self::assertIsString($path);

        try {
            // Act
            $contents = $this->component->read($path);

            // Assert
            self::assertSame('', $contents);
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function readUnavailableFileReturnsNullAndRestoresPreviousErrorHandler(): void
    {
        // Arrange
        $previousHandlerCalled = false;
        set_error_handler(static function () use (&$previousHandlerCalled): bool {
            $previousHandlerCalled = true;

            return true;
        });

        try {
            // Act
            $contents = $this->component->read('/path/that/does/not/exist');
            trigger_error('previous handler probe', E_USER_WARNING);

            // Assert
            self::assertNull($contents);
            self::assertTrue($previousHandlerCalled);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function readUnexpectedThrowablePropagatesAndRestoresPreviousErrorHandler(): void
    {
        // Arrange
        $previousHandlerCalled = false;
        set_error_handler(static function () use (&$previousHandlerCalled): bool {
            $previousHandlerCalled = true;

            return true;
        });

        try {
            // Act + Assert
            try {
                $this->component->read("/proc/invalid\0path");
                self::fail('Unexpected filesystem Throwable must be propagated.');
            } catch (\ValueError) {
                // Expected programming error from the global file_get_contents call.
            }

            trigger_error('previous handler probe', E_USER_WARNING);
            self::assertTrue($previousHandlerCalled);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function listDirectoryReturnsEntriesWithoutDotAliases(): void
    {
        // Arrange
        $path = sprintf('%s/procfs_component_%s', sys_get_temp_dir(), bin2hex(random_bytes(8)));
        self::assertTrue(mkdir($path));
        self::assertSame(0, file_put_contents($path . '/42', ''));
        self::assertSame(0, file_put_contents($path . '/77', ''));

        try {
            // Act
            $entries = $this->component->listDirectory($path);

            // Assert
            self::assertInstanceOf(ProcFilesystemDirectoryEntriesDto::class, $entries);
            self::assertSame(['42', '77'], $entries->entries);
        } finally {
            unlink($path . '/42');
            unlink($path . '/77');
            rmdir($path);
        }
    }

    #[Test]
    public function listUnavailableDirectoryReturnsNull(): void
    {
        // Arrange
        // Act
        $entries = $this->component->listDirectory('/path/that/does/not/exist');

        // Assert
        self::assertNull($entries);
    }
}
