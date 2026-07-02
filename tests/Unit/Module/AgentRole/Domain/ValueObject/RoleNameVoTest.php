<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;

#[CoversClass(RoleNameVo::class)]
final class RoleNameVoTest extends TestCase
{
    #[Test]
    public function createFromNameAcceptsValidSnakeCase(): void
    {
        self::assertSame('team_lead_alex', RoleNameVo::createFromName('team_lead_alex')->value());
    }

    #[Test]
    public function createFromNameRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RoleNameVo::createFromName('');
    }

    #[Test]
    public function createFromNameRejectsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RoleNameVo::createFromName('Team-Lead');
    }

    #[Test]
    public function createFromNameRejectsLeadingUnderscore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RoleNameVo::createFromName('_team_lead');
    }

    #[Test]
    public function createFromFileNameStripsMdAndLocaleSuffix(): void
    {
        // Arrange & Act & Assert
        self::assertSame(
            'backend_developer_levsha',
            RoleNameVo::createFromFileName('backend_developer_levsha.ru.md')->value(),
        );
        self::assertSame('team_lead', RoleNameVo::createFromFileName('team_lead.md')->value());
        self::assertSame('analyst', RoleNameVo::createFromFileName('analyst.en.md')->value());
    }

    #[Test]
    public function createFromFileNameAcceptsFullPathAndStripsDirectory(): void
    {
        self::assertSame(
            'team_lead_alex',
            RoleNameVo::createFromFileName('/var/project/docs/agents/roles/team/team_lead_alex.ru.md')->value(),
        );
    }
}
