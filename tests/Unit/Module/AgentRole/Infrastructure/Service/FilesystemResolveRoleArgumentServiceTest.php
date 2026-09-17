<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleArgumentNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LocateRoleFileServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemResolveRoleArgumentService;

/**
 * Unit-тест резолвинга аргумента «имя роли | путь к файлу роли».
 *
 * Проверяет базисы (cwd, логический PWD), лексическое сворачивание «..»
 * (семантика cd -L: без физического раскрытия симлинк-компонентов базиса)
 * и явную диагностику при ненахождении файла.
 */
#[CoversClass(FilesystemResolveRoleArgumentService::class)]
final class FilesystemResolveRoleArgumentServiceTest extends TestCase
{
    private string $temp;

    private LocateRoleFileServiceInterface $locator;

    private FilesystemResolveRoleArgumentService $service;

    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/resolve-role-argument-' . bin2hex(random_bytes(6));
        $this->locator = new class implements LocateRoleFileServiceInterface {
            /** @param list<string> $calls */
            public array $calls = [];

            #[\Override]
            public function locate(RoleNameVo $roleName): string
            {
                $this->calls[] = $roleName->value();

                return '/resolved/roles/' . $roleName->value() . '.ru.md';
            }
        };
        $this->service = new FilesystemResolveRoleArgumentService($this->locator);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temp);
    }

    #[Test]
    public function roleNameDelegatesToLocator(): void
    {
        // Act: имя без «/» и «.md» — имя роли, резолвится локатором.
        $result = $this->service->resolve('team_lead_alex', ['/any/base']);

        // Assert
        self::assertSame('/resolved/roles/team_lead_alex.ru.md', $result);
        self::assertSame(['team_lead_alex'], $this->locator->calls);
    }

    #[Test]
    public function absolutePathResolvedAsIs(): void
    {
        // Arrange
        $roleFile = $this->touch('docs/agents/roles/team/team_lead_alex.ru.md');

        // Act
        $result = $this->service->resolve($roleFile, []);

        // Assert
        self::assertSame($roleFile, $result);
        self::assertSame([], $this->locator->calls);
    }

    #[Test]
    public function relativePathResolvedFromFirstMatchingBase(): void
    {
        // Arrange
        $this->touch('docs/agents/roles/team/team_lead_alex.ru.md');

        // Act
        $result = $this->service->resolve(
            'docs/agents/roles/team/team_lead_alex.ru.md',
            ['/definitely/missing/base', $this->temp],
        );

        // Assert: первый базис без файла пропущен, второй сработал.
        self::assertSame($this->temp . '/docs/agents/roles/team/team_lead_alex.ru.md', $result);
    }

    #[Test]
    public function dotDotIsFoldedLexicallyWithoutSymlinkExpansion(): void
    {
        // Arrange — регрессия become-role: базис-симлинк. Физический realpath
        // раскрыл бы симлинк ДО сворачивания «..» и промахнулся; лексическое
        // сворачивание (cd -L) находит файл от логического местоположения.
        $fs = new Filesystem();
        $fs->mkdir($this->temp . '/project/.agents/skills');
        $fs->mkdir($this->temp . '/project/docs/agents/roles/team');
        $fs->dumpFile($this->temp . '/project/docs/agents/roles/team/team_lead_alex.ru.md', 'role');
        // «Установка» скилла: .agents/skills/become-role — симлинк в другое дерево.
        $fs->mkdir($this->temp . '/vendor/pkg/skills');
        symlink($this->temp . '/vendor/pkg/skills', $this->temp . '/project/.agents/skills/become-role');

        $logicalBase = $this->temp . '/project/.agents/skills/become-role';

        // Act: путь агента после `cd` по симлинку — «../» от логического PWD.
        $result = $this->service->resolve(
            '../../../docs/agents/roles/team/team_lead_alex.ru.md',
            [$logicalBase],
        );

        // Assert: файл найден от логического базиса (symlink не раскрыт до «..»).
        self::assertSame(
            $this->temp . '/project/docs/agents/roles/team/team_lead_alex.ru.md',
            $result,
        );
    }

    #[Test]
    public function missingPathFailsWithExplicitDiagnostics(): void
    {
        // Act
        try {
            $this->service->resolve('docs/agents/roles/team/ghost.ru.md', [$this->temp]);
            self::fail('RoleArgumentNotFoundException not thrown.');
        } catch (RoleArgumentNotFoundException $e) {
            // Assert: явная диагностика с перечнем базисов и подсказкой.
            self::assertStringContainsString('Файл роли не найден: docs/agents/roles/team/ghost.ru.md', $e->getMessage());
            self::assertStringContainsString($this->temp, $e->getMessage());
            self::assertStringContainsString('имя роли (snake_case', $e->getMessage());
        }
    }

    #[Test]
    public function dotDotAboveRootIsClamped(): void
    {
        // Arrange: «..» от корня ФС не поднимается выше корня (компонент игнорируется).
        $roleFile = $this->touch('docs/agents/roles/team/team_lead_alex.ru.md');

        // Act
        $result = $this->service->resolve('/../../../..' . $roleFile, []);

        // Assert: путь свернулся к самому файлу.
        self::assertSame($roleFile, $result);
    }

    /**
     * Создаёт файл с относительным путём от temp и возвращает абсолютный путь.
     */
    private function touch(string $relative): string
    {
        $path = $this->temp . '/' . ltrim($relative, '/');
        (new Filesystem())->dumpFile($path, 'role');

        return $path;
    }
}
