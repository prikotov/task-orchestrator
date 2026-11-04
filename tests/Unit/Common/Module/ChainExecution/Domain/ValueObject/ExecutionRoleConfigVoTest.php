<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;

#[CoversClass(ExecutionRoleConfigVo::class)]
final class ExecutionRoleConfigVoTest extends TestCase
{
    /**
     * @param array<string, list<string>> $commands
     * @param list<string> $expected
     */
    #[DataProvider('complexityProvider')]
    public function testResolveCommandSelectsNearestHigherThenLower(array $commands, int $complexity, array $expected): void
    {
        $config = new ExecutionRoleConfigVo(command: $commands === [] ? ['legacy'] : [], commandsByComplexity: $commands);

        self::assertSame($expected, $config->resolveCommand($complexity));
    }

    public static function complexityProvider(): iterable
    {
        $partial = ['C1' => ['one'], 'C3' => ['three'], 'C4' => ['four']];
        yield 'C0 rounds up' => [$partial, 0, ['one']];
        yield 'C1 exact' => [$partial, 1, ['one']];
        yield 'C2 rounds up' => [$partial, 2, ['three']];
        yield 'C3 exact' => [$partial, 3, ['three']];
        yield 'C4 exact' => [$partial, 4, ['four']];
        yield 'C5 rounds down' => [$partial, 5, ['four']];
        yield 'single lower command' => [['C1' => ['one']], 5, ['one']];
        yield 'single higher command' => [['C4' => ['four']], 0, ['four']];
        yield 'legacy command' => [[], 3, ['legacy']];
    }

    public function testBaseCommandActsAsC3(): void
    {
        $config = new ExecutionRoleConfigVo(
            command: ['default'],
            commandsByComplexity: ['C1' => ['one'], 'C4' => ['four']],
        );

        self::assertSame(['one'], $config->resolveCommand(0));
        self::assertSame(['default'], $config->resolveCommand(2));
        self::assertSame(['default'], $config->resolveCommand(3));
        self::assertSame(['four'], $config->resolveCommand(5));
    }
}
