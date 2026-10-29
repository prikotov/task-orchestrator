<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\Layout\HostPackageLayoutResolver;
use TaskOrchestrator\Common\Component\Layout\LayoutResourceNotFoundException;

/**
 * Unit-тест чистого резолвера runtime layout-ресурсов
 * {@see HostPackageLayoutResolver} (без Symfony-зависимостей).
 *
 * Покрывает контракт host → package → явное исключение для всех трёх ресурсов
 * (roles_dir, skills_dir, chains_yaml), нормализацию граничных слэшей корней и
 * защиту конструктора от пустых корней. Негативная ветка «ни одного ресурса» —
 * контракт битой сборки: возврат несуществующего пути запрещён, диагностика
 * безопасная (без несуществующих абсолютных путей).
 */
#[CoversClass(HostPackageLayoutResolver::class)]
final class HostPackageLayoutResolverTest extends TestCase
{
    private string $temp;

    private string $hostRoot;

    private string $packageRoot;

    #[\Override]
    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/layout-resolver-' . bin2hex(random_bytes(6));
        $this->hostRoot = $this->temp . '/host';
        $this->packageRoot = $this->temp . '/package';
        mkdir($this->hostRoot, 0777, true);
        mkdir($this->packageRoot, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->temp);
    }

    #[Test]
    public function hostResourceWinsWhenItExists(): void
    {
        mkdir($this->hostRoot . '/docs/agents/roles/team', 0777, true);
        mkdir($this->hostRoot . '/docs/agents/skills', 0777, true);
        mkdir($this->hostRoot . '/config', 0777, true);
        file_put_contents($this->hostRoot . '/config/chains.yaml', "chains: []\n");

        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        self::assertSame($this->hostRoot . '/docs/agents/roles/team', $resolver->resolveRolesDir());
        self::assertSame($this->hostRoot . '/docs/agents/skills', $resolver->resolveSkillsDir());
        self::assertSame($this->hostRoot . '/config/chains.yaml', $resolver->resolveChainsYaml());
    }

    #[Test]
    public function packageResourceIsFallbackWhenHostResourceMissing(): void
    {
        // Host пуст — все три ресурса отпадают в package (fallback упакованных
        // ресурсов пакета/PHAR).
        mkdir($this->packageRoot . '/docs/agents/roles/team', 0777, true);
        mkdir($this->packageRoot . '/docs/agents/skills', 0777, true);
        mkdir($this->packageRoot . '/config', 0777, true);
        file_put_contents($this->packageRoot . '/config/chains.yaml', "chains: []\n");

        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        self::assertSame($this->packageRoot . '/docs/agents/roles/team', $resolver->resolveRolesDir());
        self::assertSame($this->packageRoot . '/docs/agents/skills', $resolver->resolveSkillsDir());
        self::assertSame($this->packageRoot . '/config/chains.yaml', $resolver->resolveChainsYaml());
    }

    #[Test]
    public function missingDirectoryInBothRootsThrowsExplicitException(): void
    {
        // Ни host-, ни package-ресурса — явное исключение битой сборки
        // (возврат несуществующего пути запрещён).
        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        try {
            $resolver->resolveRolesDir();
            self::fail('Ожидалось исключение при отсутствии host- и package-ресурса.');
        } catch (LayoutResourceNotFoundException $e) {
            self::assertStringContainsString(
                'Ни host-, ни package-ресурс не найден — дистрибутив повреждён',
                $e->getMessage(),
            );
            // Безопасная диагностика: относительный путь ресурса, без
            // несуществующих абсолютных путей.
            self::assertStringContainsString(HostPackageLayoutResolver::ROLES_DIR_RELATIVE_PATH, $e->getMessage());
            self::assertStringNotContainsString($this->hostRoot, $e->getMessage());
            self::assertStringNotContainsString($this->packageRoot, $e->getMessage());
        }
    }

    #[Test]
    public function missingFileInBothRootsThrowsExplicitException(): void
    {
        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        $this->expectException(LayoutResourceNotFoundException::class);
        $this->expectExceptionMessage('Ни host-, ни package-ресурс не найден');

        $resolver->resolveChainsYaml();
    }

    #[Test]
    public function directoryCandidateDoesNotSatisfyFileResource(): void
    {
        // Строгость типов ресурса: каталог config/chains.yaml (а не файл) не
        // принимается за chains_yaml — исключение, а не молчаливый возврат.
        mkdir($this->packageRoot . '/config/chains.yaml', 0777, true);

        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        $this->expectException(LayoutResourceNotFoundException::class);
        $resolver->resolveChainsYaml();
    }

    #[Test]
    public function trailingSlashRootsAreNormalized(): void
    {
        mkdir($this->hostRoot . '/docs/agents/roles/team', 0777, true);

        $resolver = new HostPackageLayoutResolver($this->hostRoot . '/', $this->packageRoot . '/');

        self::assertSame(
            $this->hostRoot . '/docs/agents/roles/team',
            $resolver->resolveRolesDir(),
        );
    }

    #[Test]
    public function emptyRootIsRejected(): void
    {
        // Пустой корень — ошибка источника данных, а не подразумеваемое
        // значение по умолчанию.
        $this->expectException(\InvalidArgumentException::class);
        new HostPackageLayoutResolver('', $this->packageRoot);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
