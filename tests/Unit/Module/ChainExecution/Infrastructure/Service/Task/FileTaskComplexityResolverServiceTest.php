<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\ChainExecution\Infrastructure\Service\Task;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Task\FileTaskComplexityResolverService;

#[CoversClass(FileTaskComplexityResolverService::class)]
final class FileTaskComplexityResolverServiceTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/task-complexity-' . uniqid('', true);
        mkdir($this->basePath . '/todo', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->basePath . '/todo/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->basePath . '/todo');
        rmdir($this->basePath);
    }

    public function testResolvesComplexityFromNamedTask(): void
    {
        file_put_contents($this->basePath . '/todo/TASK-test.todo.md', "---\ncomplexity: C4\n---\n");

        self::assertSame(4, $this->resolver()->resolve('Implement TASK-test'));
    }

    public function testDefaultsToC3WhenComplexityIsMissing(): void
    {
        file_put_contents($this->basePath . '/todo/TASK-test.todo.md', "---\ntitle: Test\n---\n");

        self::assertSame(3, $this->resolver()->resolve('TASK-test'));
    }

    public function testRejectsInvalidComplexity(): void
    {
        file_put_contents($this->basePath . '/todo/TASK-test.todo.md', "---\ncomplexity: high\n---\n");

        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->resolve('TASK-test');
    }

    public function testRejectsMissingTaskIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->resolve('Free-form task');
    }

    private function resolver(): FileTaskComplexityResolverService
    {
        return new FileTaskComplexityResolverService($this->basePath);
    }
}
