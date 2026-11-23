<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Mapper\Chain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRoleConfigMapper;

/**
 * Unit-проверка маппинга секции `roles` из raw YAML ({@see YamlRoleConfigMapper}).
 *
 * Покрывает: простую команду, резолв command_profile, fail-fast валидацию
 * commands_by_complexity (уровни C0–C5, профили, inline-команды) и разбор
 * fallback-конфигурации.
 */
#[CoversClass(YamlRoleConfigMapper::class)]
final class YamlRoleConfigMapperTest extends TestCase
{
    private YamlRoleConfigMapper $mapper;

    /**
     * @var array<string, array{command: list<string>}>
     */
    private array $profiles = [
        'review' => ['command' => ['pi', '--mode', 'json']],
        'fast' => ['command' => ['pi', '--model', 'flash']],
    ];

    protected function setUp(): void
    {
        $this->mapper = new YamlRoleConfigMapper();
    }

    #[Test]
    public function mapsPlainCommandRole(): void
    {
        $roles = $this->mapper->mapToRoles([
            'architect' => ['command' => ['pi', '--model', 'gpt-4'], 'timeout' => 120],
        ], $this->profiles);

        $role = $roles['architect'];
        self::assertSame(['pi', '--model', 'gpt-4'], $role->getCommand());
        self::assertSame(120, $role->getTimeout());
        self::assertNull($role->getPromptFile());
        self::assertNull($role->getSystemPromptFile());
        self::assertNull($role->getFallback());
        self::assertSame([], $role->getCommandsByComplexity());
    }

    #[Test]
    public function resolvesCommandFromNamedProfile(): void
    {
        $roles = $this->mapper->mapToRoles([
            'reviewer' => ['command_profile' => 'review'],
        ], $this->profiles);

        self::assertSame(['pi', '--mode', 'json'], $roles['reviewer']->getCommand());
    }

    #[Test]
    public function throwsOnUnknownCommandProfile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown command profile "missing" for role "reviewer".');

        $this->mapper->mapToRoles([
            'reviewer' => ['command_profile' => 'missing'],
        ], $this->profiles);
    }

    #[Test]
    public function resolvesCommandsByComplexityFromProfilesAndInlineCommands(): void
    {
        $roles = $this->mapper->mapToRoles([
            'coder' => [
                'command_profile' => 'review',
                'commands_by_complexity' => [
                    'C0' => 'fast',
                    'C4' => ['pi', '--model', 'heavy'],
                ],
            ],
        ], $this->profiles);

        $commandsByComplexity = $roles['coder']->getCommandsByComplexity();
        self::assertSame(['pi', '--model', 'flash'], $commandsByComplexity['C0']);
        self::assertSame(['pi', '--model', 'heavy'], $commandsByComplexity['C4']);
    }

    #[Test]
    public function throwsOnInvalidComplexityLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid task complexity level "C9"');

        $this->mapper->mapToRoles([
            'coder' => [
                'command' => ['pi'],
                'commands_by_complexity' => ['C9' => 'fast'],
            ],
        ], $this->profiles);
    }

    #[Test]
    public function throwsOnEmptyInlineComplexityCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inline command for task complexity "C0" must not be empty');

        $this->mapper->mapToRoles([
            'coder' => [
                'command' => ['pi'],
                'commands_by_complexity' => ['C0' => []],
            ],
        ], $this->profiles);
    }

    #[Test]
    public function throwsOnUnknownProfileInCommandsByComplexity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown command profile "missing" for role "coder"');

        $this->mapper->mapToRoles([
            'coder' => [
                'command' => ['pi'],
                'commands_by_complexity' => ['C1' => 'missing'],
            ],
        ], $this->profiles);
    }

    #[Test]
    public function mapsFallbackCommand(): void
    {
        $roles = $this->mapper->mapToRoles([
            'coder' => [
                'command' => ['pi'],
                'fallback' => ['command' => ['codex', '--model', 'gpt-4o']],
            ],
        ], $this->profiles);

        $fallback = $roles['coder']->getFallback();
        self::assertNotNull($fallback);
        self::assertSame(['codex', '--model', 'gpt-4o'], $fallback->getCommand());
    }

    #[Test]
    public function skipsNonArrayRoleConfigs(): void
    {
        $roles = $this->mapper->mapToRoles([
            'broken' => 'not-an-array',
            'valid' => ['command' => ['pi']],
        ], $this->profiles);

        self::assertSame(['valid'], array_keys($roles));
    }
}
