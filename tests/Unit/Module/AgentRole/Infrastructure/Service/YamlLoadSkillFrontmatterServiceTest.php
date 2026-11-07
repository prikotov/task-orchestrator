<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\SkillNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Component\Frontmatter\FrontmatterYamlParser;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\YamlLoadSkillFrontmatterService;

/**
 * Unit-тест резолвинга skill по упорядоченному списку каталогов
 * {@see YamlLoadSkillFrontmatterService}.
 *
 * Покрывает семантику «host дополняет и переопределяет package»: первый
 * каталог списка, содержащий `<name>/SKILL.md`, выигрывает; одноимённый skill
 * host-проекта перекрывает package-версию (пер-скилл приоритет, а не выбор
 * одного каталога целиком); отсутствующий везде skill — fail-fast с
 * диагностикой по всем проверенным каталогам.
 */
#[CoversClass(YamlLoadSkillFrontmatterService::class)]
final class YamlLoadSkillFrontmatterServiceTest extends TestCase
{
    private string $temp;

    private string $hostDir;

    private string $packageDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/skill-frontmatter-' . bin2hex(random_bytes(6));
        $this->hostDir = $this->temp . '/host/docs/agents/skills';
        $this->packageDir = $this->temp . '/package/docs/agents/skills';
        mkdir($this->hostDir, 0777, true);
        mkdir($this->packageDir, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->temp);
    }

    #[Test]
    public function hostSkillWinsOverPackageSkillWithSameName(): void
    {
        $this->writeSkill($this->hostDir, 'deploy', 'Хост-версия deploy');
        $this->writeSkill($this->packageDir, 'deploy', 'Пакетная версия deploy');

        $service = $this->service();

        $metadata = $service->read(SkillNameVo::createFromName('deploy'));

        self::assertSame('deploy', $metadata->getName()->value());
        self::assertSame('Хост-версия deploy', $metadata->getDescription());
        self::assertStringStartsWith($this->hostDir, $metadata->getLocation());
    }

    #[Test]
    public function packageSkillResolvesWhenHostDoesNotHaveIt(): void
    {
        // host дополняет package: skill есть только в package — резолвится
        // из package, host-каталог не «перекрывает» пакет целиком.
        $this->writeSkill($this->packageDir, 'beta', 'Пакетный beta');

        $metadata = $this->service()->read(SkillNameVo::createFromName('beta'));

        self::assertSame('beta', $metadata->getName()->value());
        self::assertStringStartsWith($this->packageDir, $metadata->getLocation());
    }

    #[Test]
    public function missingEverywhereThrowsWithAllSearchedDirs(): void
    {
        $service = $this->service();

        try {
            $service->read(SkillNameVo::createFromName('unknown'));
            self::fail('Ожидалось SkillNotFoundException для skill, отсутствующего во всех каталогах.');
        } catch (SkillNotFoundException $e) {
            self::assertStringContainsString('unknown', $e->getMessage());
            self::assertStringContainsString($this->hostDir, $e->getMessage());
            self::assertStringContainsString($this->packageDir, $e->getMessage());
        }
    }

    #[Test]
    public function emptyDirsListIsRejected(): void
    {
        $service = new YamlLoadSkillFrontmatterService('  | ', new FrontmatterYamlParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Skills dirs list is empty.');

        $service->read(SkillNameVo::createFromName('any'));
    }

    private function service(): YamlLoadSkillFrontmatterService
    {
        return new YamlLoadSkillFrontmatterService(
            $this->hostDir . '|' . $this->packageDir,
            new FrontmatterYamlParser(),
        );
    }

    private function writeSkill(string $baseDir, string $name, string $description): void
    {
        $dir = $baseDir . '/' . $name;
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: {$name}\ndescription: {$description}\n---\n\n# {$name}\n",
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
