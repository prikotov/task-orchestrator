<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;






/**
 * Установка task-orchestrator в host-проекте.
 *
 * Создаёт симлинк общего skill `become-role` в `<project>/.agents/skills/`, чтобы
 * он был виден AI-инструментам (pi, codex и др.) как нативный skill через
 * кросс-клиентскую конвенцию `.agents/skills/`. Сам skill живёт в пакете
 * task-orchestrator; симлинк делает его доступным без копирования.
 *
 * В PHAR команда намеренно завершается до файловых операций: этот вторичный
 * канал дистрибуции не содержит установщик внешних skill-ресурсов.
 *
 * Idempotent: повторный запуск безопасен. Некорректный существующий симлинк
 * заменяется только с --force.
 */
#[AsCommand(
    name: 'agent:init',
    description: 'Установка task-orchestrator в host-проекте (общий skill become-role)',
)]
final class InitCommand extends Command
{
    private const string OPT_FORCE = 'force';

    private const string SKILL_RELATIVE_PATH = 'docs/agents/skills/become-role';

    private const string TARGET_RELATIVE_PATH = '.agents/skills/become-role';

    public function __construct(
        private readonly string $packageDir,
        private readonly string $basePath,
        private readonly bool $isPhar,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            self::OPT_FORCE,
            'f',
            InputOption::VALUE_NONE,
            'Пересоздать симлинк, даже если он уже существует и некорректен',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Утверждённый контракт PHAR: завершиться до любых операций с файловой
        // системой host-проекта и направить пользователя в Composer-канал.
        if ($this->isPhar) {
            $io->error('agent:init недоступен в PHAR. Используйте Composer.');
            $output->writeln('php vendor/bin/task-orchestrator agent:init');

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption(self::OPT_FORCE);

        $source = Path::canonicalize($this->packageDir . '/' . self::SKILL_RELATIVE_PATH);
        $targetDir = Path::canonicalize($this->basePath . '/.agents/skills');
        $target = Path::canonicalize($targetDir . '/' . basename(self::TARGET_RELATIVE_PATH));

        if (!is_dir($source) || !is_file($source . '/SKILL.md')) {
            $io->error(sprintf('Skill become-role не найден в пакете: %s', $source));

            return Command::FAILURE;
        }

        if ($this->isCorrectSymlink($target, $source)) {
            $io->success(sprintf('become-role уже установлен: %s -> %s', $target, $source));

            return Command::SUCCESS;
        }

        if ((is_link($target) || file_exists($target)) && !$force) {
            $io->warning(sprintf(
                'Путь существует и не является корректным симлинком become-role: %s. Используйте --force для замены.',
                $target,
            ));

            return Command::FAILURE;
        }

        $this->filesystem->mkdir($targetDir);

        if (is_link($target) || file_exists($target)) {
            $this->filesystem->remove($target);
        }

        // Относительный путь от каталога симлинка к источнику — переносим при
        // перемещении проекта целиком.
        $relativeSource = Path::makeRelative($source, $targetDir);
        $this->filesystem->symlink($relativeSource, $target);

        $io->success(sprintf('become-role установлен: %s -> %s', $target, $source));

        return Command::SUCCESS;
    }

    private function isCorrectSymlink(string $target, string $expectedSource): bool
    {
        if (!is_link($target)) {
            return false;
        }

        $linkTarget = readlink($target);
        if ($linkTarget === false) {
            return false;
        }

        $resolved = Path::canonicalize(dirname($target) . '/' . $linkTarget);

        return $resolved === Path::canonicalize($expectedSource);
    }
}
