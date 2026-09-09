<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQuery;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Prompt\GetPromptFilePath\GetPromptFilePathQuery;




/**
 * Composer-host regression test локали AI-ролей (env TASK_ORCHESTRATOR_LOCALE).
 *
 * Воспроизводит сценарий host-проекта, подключившего пакет через Composer:
 * один host root, стабильный корень кеша контейнера и два последовательных
 * запуска с TASK_ORCHESTRATOR_LOCALE=en → TASK_ORCHESTRATOR_LOCALE=ru БЕЗ
 * очистки кеша между запусками. Проверяется наблюдаемый результат через
 * публичные точки приложения (QueryBus), а не только значение параметра:
 *   - AgentRole (ResolveRoleSkills): относительный путь role file и язык
 *     заголовка каталога skills;
 *   - ChainExecution (GetPromptFilePath): путь role file для построения
 *     prompt (запроса для AI) — согласован с AgentRole.
 *
 * Здесь проверяется именно agent-role locale (локаль AI-ролей); локаль
 * Symfony-переводчика (kernel.default_locale) независима и покрыта в
 * {@see KernelIntegrationTest}.
 */
#[CoversClass(Kernel::class)]
final class ComposerHostLocaleRegressionTest extends TestCase
{
    private const string RELEASE_VERSION = '0.5.3';

    private string $hostRoot;

    private string $cacheRoot;

    private ?Kernel $bootedKernel = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->hostRoot = sys_get_temp_dir() . '/to-composer-host-locale-' . bin2hex(random_bytes(6));
        $this->cacheRoot = $this->hostRoot . '/var/cache/task-orchestrator/' . self::RELEASE_VERSION . '/test';

        // Роли: en- и ru-локализация с уникальным содержимым и общим skill.
        mkdir($this->hostRoot . '/docs/agents/roles/team', 0777, true);
        mkdir($this->hostSkillsDir() . '/tester-skill', 0777, true);

        file_put_contents(
            $this->hostRoot . '/docs/agents/roles/team/tester.en.md',
            <<<'MD'
                ---
                agent: tester
                skills:
                  - tester-skill
                ---

                # Tester (en)

                tester-en-unique-body
                MD,
        );
        file_put_contents(
            $this->hostRoot . '/docs/agents/roles/team/tester.ru.md',
            <<<'MD'
                ---
                agent: tester
                skills:
                  - tester-skill
                ---

                # Тестировщик (ru)

                tester-ru-уникальное-содержимое
                MD,
        );
        file_put_contents(
            $this->hostSkillsDir() . '/tester-skill/SKILL.md',
            <<<'MD'
                ---
                name: tester-skill
                description: Host skill for locale regression
                ---

                # Tester Skill
                MD,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->shutdownKernel();
        $this->removeDirectory($this->hostRoot);
    }

    #[Test]
    public function roleFileAndSkillCatalogFollowLocaleSwitchOnSameComposerHostCacheRoot(): void
    {
        $restoreCacheDir = $this->isolateEnvVar('APP_CACHE_DIR', null);
        $restoreReleaseVersion = $this->isolateEnvVar('APP_RELEASE_VERSION', self::RELEASE_VERSION);

        try {
            // --- Первый запуск: TASK_ORCHESTRATOR_LOCALE=en. ---
            $restoreEn = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', 'en');
            try {
                $queryBus = $this->bootHostQueryBus();

                // AgentRole: выбрана en-локализация role file.
                $agentRolePath = $this->resolveAgentRoleFilePath($queryBus);
                self::assertSame('docs/agents/roles/team/tester.en.md', $agentRolePath);

                // ChainExecution: та же локализация роли для построения prompt.
                $chainPath = $this->queryPromptFilePath($queryBus);
                self::assertSame('docs/agents/roles/team/tester.en.md', $chainPath);

                // Каталог skills использует англоязычный заголовок (default `en`).
                self::assertStringContainsString(
                    'The following skills provide specialized instructions',
                    $this->resolveSkillCatalog($queryBus),
                );

                // Наблюдаемое содержимое — уникальный en-маркер выбранного файла.
                self::assertStringContainsString(
                    'tester-en-unique-body',
                    (string) file_get_contents($this->hostRoot . '/' . $agentRolePath),
                );
            } finally {
                $restoreEn();
            }

            // Кеш скомпилирован и НЕ удаляется между запусками.
            self::assertFileExists($this->cacheRoot);
            self::assertNotEmpty(
                glob($this->cacheRoot . '/*Container.php'),
                'Первый запуск должен скомпилировать контейнер в стабильном корне кеша host-проекта',
            );

            // --- Второй запуск: TASK_ORCHESTRATOR_LOCALE=ru, тот же корень кеша. ---
            $restoreRu = $this->isolateEnvVar('TASK_ORCHESTRATOR_LOCALE', 'ru');
            try {
                $queryBus = $this->bootHostQueryBus();

                // AgentRole: выбрана ru-локализация role file — без очистки кеша.
                $agentRolePath = $this->resolveAgentRoleFilePath($queryBus);
                self::assertSame('docs/agents/roles/team/tester.ru.md', $agentRolePath);

                // ChainExecution согласована с AgentRole (единый источник локали).
                self::assertSame('docs/agents/roles/team/tester.ru.md', $this->queryPromptFilePath($queryBus));

                // Каталог skills использует русскоязычный заголовок.
                self::assertStringContainsString(
                    'Следующие skills предоставляют специализированные инструкции',
                    $this->resolveSkillCatalog($queryBus),
                );

                // Наблюдаемое содержимое — уникальный ru-маркер выбранного файла.
                self::assertStringContainsString(
                    'tester-ru-уникальное-содержимое',
                    (string) file_get_contents($this->hostRoot . '/' . $agentRolePath),
                );
            } finally {
                $restoreRu();
            }
        } finally {
            $restoreReleaseVersion();
            $restoreCacheDir();
        }
    }

    /**
     * Boot ядра в контексте host-проекта (двойной контекст Composer-пакета:
     * роли/цепочки/кеш — в host, конфигурация — в пакете). Каждый запуск —
     * самостоятельный процесс-эквивалент: предыдущее ядро выключается, кеш
     * сохраняется.
     */
    private function bootHostQueryBus(): QueryBusComponentInterface
    {
        $this->shutdownKernel();
        $kernel = new Kernel('test', false, $this->hostRoot);
        $kernel->boot();
        $this->bootedKernel = $kernel;

        /** @var QueryBusComponentInterface $queryBus */
        $queryBus = $kernel->getContainer()->get(QueryBusComponentInterface::class);
        self::assertInstanceOf(QueryBusComponentInterface::class, $queryBus);

        return $queryBus;
    }

    private function shutdownKernel(): void
    {
        $this->bootedKernel?->shutdown();
        $this->bootedKernel = null;
    }

    private function resolveAgentRoleFilePath(QueryBusComponentInterface $queryBus): string
    {
        $result = $queryBus->query(new ResolveRoleSkillsQuery('tester'));
        self::assertInstanceOf(ResolveRoleSkillsResultDto::class, $result);

        return $result->roleFilePath;
    }

    private function resolveSkillCatalog(QueryBusComponentInterface $queryBus): string
    {
        $result = $queryBus->query(new ResolveRoleSkillsQuery('tester'));
        self::assertInstanceOf(ResolveRoleSkillsResultDto::class, $result);

        return $result->catalogBlock;
    }

    private function queryPromptFilePath(QueryBusComponentInterface $queryBus): string
    {
        $path = $queryBus->query(new GetPromptFilePathQuery('tester'));
        self::assertIsString($path);

        return $path;
    }

    private function hostSkillsDir(): string
    {
        return $this->hostRoot . '/docs/agents/skills';
    }

    /**
     * Изолирует env-переменную на время теста и возвращает restore-callback
     * (вызвать в finally). $value = null — переменная снимается.
     */
    private function isolateEnvVar(string $name, ?string $value): \Closure
    {
        $hadServer = array_key_exists($name, $_SERVER);
        $server = $_SERVER[$name] ?? null;
        $hadEnv = array_key_exists($name, $_ENV);
        $env = $_ENV[$name] ?? null;
        $getenv = getenv($name); // false, когда переменная не задана

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }

        return static function () use ($name, $hadServer, $server, $hadEnv, $env, $getenv): void {
            if ($hadServer) {
                $_SERVER[$name] = $server;
            } else {
                unset($_SERVER[$name]);
            }
            if ($hadEnv) {
                $_ENV[$name] = $env;
            } else {
                unset($_ENV[$name]);
            }
            if ($getenv !== false) {
                putenv($name . '=' . $getenv);
            } else {
                putenv($name);
            }
        };
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
