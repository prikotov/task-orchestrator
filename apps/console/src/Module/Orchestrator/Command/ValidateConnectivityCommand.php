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
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum\ConnectivityStatusEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ConnectivityRoleResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityCommand as ValidateConnectivityUseCaseCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityCommandHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityResultDto;

#[AsCommand(
    name: 'validate:connectivity',
    description: 'Проверить, что top-level роли из chains.yaml запускаются и отвечают',
)]
final class ValidateConnectivityCommand extends Command
{
    private const string OPT_CONFIG = 'config';
    private const string OPT_ROLE = 'role';
    private const string OPT_TIMEOUT = 'timeout';
    private const string OPT_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly ValidateConnectivityCommandHandler $handler,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption(self::OPT_CONFIG, null, InputOption::VALUE_OPTIONAL, 'Путь к файлу chains.yaml')
            ->addOption(self::OPT_ROLE, null, InputOption::VALUE_OPTIONAL, 'Проверить только указанную роль')
            ->addOption(self::OPT_TIMEOUT, null, InputOption::VALUE_OPTIONAL, 'Таймаут на роль в секундах', '30')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Показать цели без запуска процессов/LLM');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = ($this->handler)(new ValidateConnectivityUseCaseCommand(
                configPath: $this->normalizeOptionalString($input->getOption(self::OPT_CONFIG)),
                roleName: $this->normalizeOptionalString($input->getOption(self::OPT_ROLE)),
                timeout: $this->parsePositiveInt($input->getOption(self::OPT_TIMEOUT), '--timeout'),
                dryRun: (bool) $input->getOption(self::OPT_DRY_RUN),
            ));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->renderResult($io, $result);

        return $result->hasFailures ? Command::FAILURE : Command::SUCCESS;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function parsePositiveInt(mixed $value, string $optionName): int
    {
        if (!is_scalar($value) || $value === '') {
            throw new \InvalidArgumentException(sprintf('%s must be a positive integer.', $optionName));
        }

        if (!ctype_digit((string) $value)) {
            throw new \InvalidArgumentException(sprintf('%s must be a positive integer.', $optionName));
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new \InvalidArgumentException(sprintf('%s must be a positive integer.', $optionName));
        }

        return $intValue;
    }

    private function renderResult(SymfonyStyle $io, ValidateConnectivityResultDto $result): void
    {
        if ($result->dryRun) {
            $io->section('Connectivity targets (dry-run)');
        } else {
            $io->section('Connectivity results');
        }

        $io->table(
            ['Role', 'Status', 'Time', 'Error'],
            array_map($this->mapTableRow(...), $result->results),
        );

        $this->renderSummary($io, $result);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function mapTableRow(ConnectivityRoleResultDto $row): array
    {
        return [
            $row->role,
            $this->formatStatus($row->status),
            $row->durationSeconds !== null ? sprintf('%.1fs', $row->durationSeconds) : '-',
            $row->error ?? $row->commandPreview ?? '',
        ];
    }

    private function formatStatus(ConnectivityStatusEnum $status): string
    {
        return match ($status) {
            ConnectivityStatusEnum::ok => '<fg=green>✓ OK</>',
            ConnectivityStatusEnum::fail => '<fg=red>✗ FAIL</>',
            ConnectivityStatusEnum::timeout => '<fg=red>✗ TIMEOUT</>',
            ConnectivityStatusEnum::dryRun => '<fg=yellow>DRY RUN</>',
        };
    }

    private function renderSummary(SymfonyStyle $io, ValidateConnectivityResultDto $result): void
    {
        if ($result->dryRun) {
            $io->success(sprintf('%d target(s), dry-run: no processes started.', count($result->results)));

            return;
        }

        $total = count($result->results);
        $passed = 0;
        $failed = 0;
        $timeout = 0;

        foreach ($result->results as $row) {
            match ($row->status) {
                ConnectivityStatusEnum::ok => $passed++,
                ConnectivityStatusEnum::timeout => $timeout++,
                default => $failed++,
            };
        }

        $message = sprintf('%d/%d passed, %d failed, %d timeout', $passed, $total, $failed, $timeout);
        if ($result->hasFailures) {
            $io->warning($message);

            return;
        }

        $io->success($message);
    }
}
