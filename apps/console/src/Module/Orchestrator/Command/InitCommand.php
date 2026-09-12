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
use Symfony\Component\Lock\LockFactory;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallOutcomeEnum;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\InstallBecomeRoleServiceInterface;

/**
 * Установка task-orchestrator в host-проекте (команда `agent:init`).
 *
 * Транспортная граница: разбирает --force, блокирует конкурентный запуск,
 * делегирует файловую установку skill `become-role` специализированному
 * Presentation-сервису {@see InstallBecomeRoleServiceInterface} и отображает
 * его результат ({@see \TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallResultDto}),
 * мапя исход {@see BecomeRoleInstallOutcomeEnum} в способ вывода и код
 * завершения. Механизм установки зависит от режима дистрибуции:
 * source/Composer — относительный симлинк на skill пакета, PHAR — управляемая
 * копия с runtime-привязкой к физическому пути PHAR (детали и гарантии — в
 * сервисе). Повторный запуск идемпотентен; отличающийся целевой объект
 * заменяется только с --force.
 */
#[AsCommand(
    name: 'agent:init',
    description: 'Установка task-orchestrator в host-проекте (общий skill become-role)',
)]
final class InitCommand extends Command
{
    private const string OPT_FORCE = 'force';

    public const string LOCK_RESOURCE = 'command:agent:init';

    public function __construct(
        private readonly InstallBecomeRoleServiceInterface $installer,
        private readonly LockFactory $lockFactory,
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
            'Заменить существующую установку become-role, если она отличается от ожидаемой',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $force = (bool) $input->getOption(self::OPT_FORCE);

        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE);

        if (!$lock->acquire()) {
            $io->warning(sprintf('Команда "%s" уже выполняется. Пропускаем.', $this->getName() ?? static::class));

            return Command::SUCCESS;
        }

        try {
            $result = $this->installer->install($force);
        } finally {
            $lock->release();
        }

        return match ($result->outcome) {
            BecomeRoleInstallOutcomeEnum::installed,
            BecomeRoleInstallOutcomeEnum::alreadyInstalled => $this->renderSuccess($io, $result->message),
            BecomeRoleInstallOutcomeEnum::conflict => $this->renderWarning($io, $result->message),
            BecomeRoleInstallOutcomeEnum::sourceMissing,
            BecomeRoleInstallOutcomeEnum::sourceInvalid,
            BecomeRoleInstallOutcomeEnum::error => $this->renderError($io, $result->message),
        };
    }

    private function renderSuccess(SymfonyStyle $io, string $message): int
    {
        $io->success($message);

        return Command::SUCCESS;
    }

    private function renderWarning(SymfonyStyle $io, string $message): int
    {
        $io->warning($message);

        return Command::FAILURE;
    }

    private function renderError(SymfonyStyle $io, string $message): int
    {
        $io->error($message);

        return Command::FAILURE;
    }
}
