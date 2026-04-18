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
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentResultDto;

#[AsCommand(
    name: 'app:agent:run',
    description: 'Запустить одного AI-агента с указанной ролью',
)]
final class RunCommand extends Command
{
    private const string OPT_ROLE = 'role';
    private const string OPT_TASK = 'task';
    private const string OPT_RUNNER = 'runner';
    private const string OPT_MODEL = 'model';
    private const string OPT_TOOLS = 'tools';
    private const string OPT_WORKING_DIR = 'working-dir';
    private const string OPT_NO_CONTEXT_FILES = 'no-context-files';

    public function __construct(
        private readonly RunAgentCommandHandler $agentHandler,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption(self::OPT_ROLE, 'r', InputOption::VALUE_REQUIRED, 'Роль агента (например, system_analyst)')
            ->addOption(self::OPT_TASK, 't', InputOption::VALUE_REQUIRED, 'Задача для агента')
            ->addOption(self::OPT_RUNNER, null, InputOption::VALUE_OPTIONAL, 'Движок (по умолчанию: pi)', 'pi')
            ->addOption(self::OPT_MODEL, 'm', InputOption::VALUE_OPTIONAL, 'Модель LLM')
            ->addOption(self::OPT_TOOLS, null, InputOption::VALUE_OPTIONAL, 'Список инструментов')
            ->addOption(self::OPT_WORKING_DIR, 'd', InputOption::VALUE_OPTIONAL, 'Рабочая директория')
            ->addOption(self::OPT_NO_CONTEXT_FILES, null, InputOption::VALUE_NONE, 'Отключить автоматическую загрузку контекстных файлов (AGENTS.md, CLAUDE.md)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $role */
        $role = $input->getOption(self::OPT_ROLE);
        /** @var string $task */
        $task = $input->getOption(self::OPT_TASK);
        $runner = $input->getOption(self::OPT_RUNNER);
        $model = $input->getOption(self::OPT_MODEL);
        $tools = $input->getOption(self::OPT_TOOLS);
        $workingDir = $input->getOption(self::OPT_WORKING_DIR);
        $noContextFiles = (bool) $input->getOption(self::OPT_NO_CONTEXT_FILES);

        $io->text(sprintf('🤖 Running agent: %s @ %s', $role, $runner ?? 'pi'));

        try {
            /** @var RunAgentResultDto $result */
            $result = ($this->agentHandler)(new RunAgentCommand(
                role: $role,
                task: $task,
                runner: $runner !== null && $runner !== '' ? $runner : null,
                model: $model !== null && $model !== '' ? $model : null,
                tools: $tools !== null && $tools !== '' ? $tools : null,
                workingDir: $workingDir !== null && $workingDir !== '' ? $workingDir : null,
                noContextFiles: $noContextFiles,
            ));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($result->isError) {
            $io->error(sprintf('Agent error: %s', $result->errorMessage ?? 'Unknown error'));

            return Command::FAILURE;
        }

        $io->section('Результат');
        $io->text($result->outputText);

        if ($output->isVerbose()) {
            $io->section('Метрики');
            $io->definitionList(
                ['Model' => $result->model ?? 'N/A'],
                ['Input tokens' => (string) $result->inputTokens],
                ['Output tokens' => (string) $result->outputTokens],
                ['Cost' => sprintf('$%.4f', $result->cost)],
                ['Turns' => (string) $result->turns],
            );
        }

        return Command::SUCCESS;
    }
}
