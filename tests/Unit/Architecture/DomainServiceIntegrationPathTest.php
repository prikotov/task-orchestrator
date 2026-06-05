<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[CoversNothing]
final class DomainServiceIntegrationPathTest extends TestCase
{
    #[Test]
    public function domainServiceDoesNotUseIntegrationContextPath(): void
    {
        $moduleDir = realpath(__DIR__ . '/../../../src/Module');
        self::assertIsString($moduleDir);

        $forbiddenFiles = [];
        $forbiddenSegment = implode(DIRECTORY_SEPARATOR, ['Domain', 'Service', 'Integration']) . DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relativePath = str_replace($moduleDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (str_contains($relativePath, DIRECTORY_SEPARATOR . $forbiddenSegment)) {
                $forbiddenFiles[] = $relativePath;
            }
        }

        self::assertEmpty(
            $forbiddenFiles,
            'Domain Service contracts must use business context paths, not technical Integration context.',
        );
    }
}
