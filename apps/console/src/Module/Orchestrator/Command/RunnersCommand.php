<?php

declare(strict_types=1);

namespace Console\Module\Orchestrator\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\RunnerDto;

#[AsCommand(
    name: 'app:agent:runners',
    description: 'Показать доступные движки AI-агентов',
)]
final class RunnersCommand extends Command
{
    public function __construct(
        private readonly GetRunnersQueryHandler $runnersHandler,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<RunnerDto> $runners */
        $runners = ($this->runnersHandler)(new GetRunnersQuery());

        if (count($runners) === 0) {
            $io->warning('Нет зарегистрированных движков.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($runners as $runner) {
            $rows[] = [
                $runner->name,
                $runner->isAvailable ? '✓ Available' : '✗ Unavailable',
            ];
        }

        $io->table(['Runner', 'Status'], $rows);

        return Command::SUCCESS;
    }
}
