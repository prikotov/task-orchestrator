<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationErrorDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ChainConfigValidatorInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ResolveExitCodeServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainHandlerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportHandlerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

#[AsCommand(
    name: 'app:agent:orchestrate',
    description: 'Оркестрация AI-агентов по цепочке (static/dynamic)',
)]

/**
 * @techdebt 2026-04-24: Command зависит от Domain\ChainLoaderInterface и Domain\ChainDefinitionVo.
 * Нужно вынести загрузку chain в Application-слой (ChainDefinitionDto + ChainLoaderApplicationInterface).
 *
 * Exit codes:
 *
 * | Code | Constant        | Meaning                                    |
 * |------|-----------------|--------------------------------------------|
 * | 0    | success         | Цепочка выполнена успешно                  |
 * | 1    | chainFailed     | Ошибка выполнения шага/агента              |
 * | 3    | chainNotFound   | Запрошенная цепочка не найдена             |
 * | 4    | budgetExceeded  | Превышен бюджет цепочки                    |
 * | 5    | invalidConfig   | Неверная конфигурация цепочки или аргументы|
 * | 6    | timeout         | Превышен таймаут (зарезервирован)          |
 */
final class OrchestrateCommand extends Command
{
    private const string ARG_TASK = 'task';
    private const string OPT_CHAIN = 'chain';
    private const string OPT_WORKING_DIR = 'working-dir';
    private const string OPT_DRY_RUN = 'dry-run';
    private const string OPT_TIMEOUT = 'timeout';
    private const string OPT_TOPIC = 'topic';
    private const string OPT_MAX_ROUNDS = 'max-rounds';
    private const string OPT_FACILITATOR = 'facilitator';
    private const string OPT_PARTICIPANTS = 'participants';
    private const string OPT_RESUME = 'resume';
    private const string OPT_NO_AUDIT_LOG = 'no-audit-log';
    private const string OPT_REPORT_FORMAT = 'report-format';
    private const string OPT_REPORT_FILE = 'report-file';
    private const string OPT_NO_CONTEXT_FILES = 'no-context-files';
    private const string OPT_VALIDATE_CONFIG = 'validate-config';

    public const string LOCK_RESOURCE = 'command:agent:orchestrate';

    public function __construct(
        private readonly OrchestrateChainHandlerInterface $orchestrateHandler,
        private readonly GenerateReportHandlerInterface $reportHandler,
        private readonly LockFactory $lockFactory,
        private readonly ChainLoaderInterface $chainLoader,
        private readonly ResolveExitCodeServiceInterface $exitCodeResolver,
        private readonly ChainConfigValidatorInterface $chainConfigValidator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_TASK, InputArgument::REQUIRED, 'Задача для оркестрации')
            ->addOption(self::OPT_CHAIN, 'c', InputOption::VALUE_OPTIONAL, 'Имя цепочки', 'implement')
            ->addOption(self::OPT_WORKING_DIR, 'd', InputOption::VALUE_OPTIONAL, 'Рабочая директория')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Показать план без запуска')
            ->addOption(self::OPT_TIMEOUT, 't', InputOption::VALUE_OPTIONAL, 'Таймаут на шаг (секунды)', '1800')
            ->addOption(self::OPT_TOPIC, null, InputOption::VALUE_OPTIONAL, 'Тема для dynamic-цепочки (по умолчанию = task)')
            ->addOption(self::OPT_MAX_ROUNDS, null, InputOption::VALUE_OPTIONAL, 'Макс. раундов (dynamic)')
            ->addOption(self::OPT_FACILITATOR, null, InputOption::VALUE_OPTIONAL, 'Роль фасилитатора (dynamic)')
            ->addOption(self::OPT_PARTICIPANTS, null, InputOption::VALUE_OPTIONAL, 'Участники через запятую (dynamic)')
            ->addOption(self::OPT_RESUME, null, InputOption::VALUE_OPTIONAL, 'Путь к директории сессии для resume')
            ->addOption(self::OPT_NO_AUDIT_LOG, null, InputOption::VALUE_NONE, 'Отключить audit-логирование')
            ->addOption(self::OPT_REPORT_FORMAT, null, InputOption::VALUE_OPTIONAL, 'Формат отчёта: text|json (none — отключить)', 'text')
            ->addOption(self::OPT_REPORT_FILE, null, InputOption::VALUE_OPTIONAL, 'Путь к файлу для записи отчёта')
            ->addOption(self::OPT_NO_CONTEXT_FILES, null, InputOption::VALUE_NONE, 'Отключить автоматическую загрузку контекстных файлов (AGENTS.md, CLAUDE.md)')
            ->addOption(self::OPT_VALIDATE_CONFIG, null, InputOption::VALUE_NONE, 'Проверить конфигурацию цепочки без запуска оркестрации');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE);

        // ── --validate-config: проверить конфиг и выйти без запуска ──
        /** @var bool $validateConfig */
        $validateConfig = $input->getOption(self::OPT_VALIDATE_CONFIG);
        if ($validateConfig) {
            return $this->executeValidateConfig($input, $io);
        }

        if (!$lock->acquire()) {
            $io->warning(sprintf('Команда "%s" уже выполняется. Пропускаем.', $this->getName() ?? static::class));

            return OrchestrateExitCodeEnum::success->value;
        }

        try {
            /** @var string $task */
            $task = $input->getArgument(self::ARG_TASK);
            /** @var string $chainName */
            $chainName = $input->getOption(self::OPT_CHAIN);
            /** @var bool $dryRun */
            $dryRun = $input->getOption(self::OPT_DRY_RUN);
            $timeout = (int) $input->getOption(self::OPT_TIMEOUT);

            /** @var string|null $workingDir */
            $workingDir = $input->getOption(self::OPT_WORKING_DIR);

            // Dynamic-опции
            /** @var string|null $topic */
            $topic = $input->getOption(self::OPT_TOPIC);
            /** @var string|null $maxRoundsStr */
            $maxRoundsStr = $input->getOption(self::OPT_MAX_ROUNDS);
            $maxRounds = $maxRoundsStr !== null ? (int) $maxRoundsStr : null;
            /** @var string|null $facilitator */
            $facilitator = $input->getOption(self::OPT_FACILITATOR);
            /** @var string|null $participantsStr */
            $participantsStr = $input->getOption(self::OPT_PARTICIPANTS);
            $participants = $participantsStr !== null ? array_map('trim', explode(',', $participantsStr)) : null;
            /** @var string|null $resumeDir */
            $resumeDir = $input->getOption(self::OPT_RESUME);
            /** @var bool $noAuditLog */
            $noAuditLog = $input->getOption(self::OPT_NO_AUDIT_LOG);
            $noContextFiles = (bool) $input->getOption(self::OPT_NO_CONTEXT_FILES);

            if ($resumeDir !== null && $resumeDir !== '') {
                $io->section(sprintf('🔄 Resuming session: %s', $resumeDir));

                /** @var OrchestrateChainResultDto $result */
                $result = ($this->orchestrateHandler)(new OrchestrateChainCommand(
                    chainName: $chainName,
                    task: $task,
                    workingDir: $workingDir !== null && $workingDir !== '' ? $workingDir : null,
                    timeout: $timeout,
                    resumeDir: $resumeDir,
                    noAuditLog: $noAuditLog,
                    noContextFiles: $noContextFiles,
                ));

                // Resume всегда резолвит exit code как dynamic — информация о типе цепочки не сохраняется в сессии.
                $this->renderDynamicResult($io, $result);

                return $this->exitCodeResolver->resolveFromResult($result, true)->value;
            }

            $chain = $this->chainLoader->load($chainName);
            $isDynamic = $chain->isDynamic();

            if ($dryRun) {
                $this->renderDryRun($io, $chainName, $task, $chain, $isDynamic, $topic, $facilitator, $participants, $maxRounds);

                return OrchestrateExitCodeEnum::success->value;
            }

            $io->section(sprintf('🚀 Orchestrating: %s (%s)', $chainName, $isDynamic ? 'dynamic' : 'static'));

            /** @var OrchestrateChainResultDto $result */
            $result = ($this->orchestrateHandler)(new OrchestrateChainCommand(
                chainName: $chainName,
                task: $task,
                workingDir: $workingDir !== null && $workingDir !== '' ? $workingDir : null,
                timeout: $timeout,
                topic: $topic !== null && $topic !== '' ? $topic : null,
                maxRounds: $maxRounds,
                facilitator: $facilitator !== null && $facilitator !== '' ? $facilitator : null,
                participants: $participants,
                resumeDir: null,
                noAuditLog: $noAuditLog,
                noContextFiles: $noContextFiles,
            ));

            // Генерация отчёта (если задан формат)
            /** @var string $reportFormat */
            $reportFormat = $input->getOption(self::OPT_REPORT_FORMAT);
            /** @var string|null $reportFile */
            $reportFile = $input->getOption(self::OPT_REPORT_FILE);

            if ($reportFormat !== '' && $reportFormat !== 'none') {
                $formatEnum = ReportFormatEnum::from($reportFormat);

                $reportResult = ($this->reportHandler)(
                    new GenerateReportQuery($result, $chainName, $task, $formatEnum),
                );

                $this->writeReport($reportResult->content, $reportFile, $io);
            }

            if ($isDynamic) {
                $this->renderDynamicResult($io, $result);
            } else {
                $this->renderStaticResult($io, $result);
            }

            return $this->exitCodeResolver->resolveFromResult($result, $isDynamic)->value;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return $this->exitCodeResolver->resolveFromThrowable($e)->value;
        } finally {
            $lock->release();
        }
    }

    /**
     * Обрабатывает флаг --validate-config: валидирует конфигурацию цепочки и выводит результат.
     *
     * Если указана опция --chain — валидирует конкретную цепочку,
     * иначе — все цепочки.
     */
    private function executeValidateConfig(InputInterface $input, SymfonyStyle $io): int
    {
        // Если --chain передана явно — валидируем конкретную цепочку
        if ($input->hasParameterOption('--chain') || $input->hasParameterOption('-c')) {
            /** @var string $chainName */
            $chainName = $input->getOption(self::OPT_CHAIN);
            $result = $this->chainConfigValidator->validateChain($chainName);
        } else {
            $result = $this->chainConfigValidator->validateAll();
        }

        if ($result->isValid) {
            $io->success(sprintf(
                '✅ Config is valid (%d chain(s): %s).',
                count($result->validatedChains),
                implode(', ', $result->validatedChains),
            ));

            return OrchestrateExitCodeEnum::success->value;
        }

        $io->error('❌ Config validation failed:');
        foreach ($result->errors as $error) {
            $fieldSuffix = $error->field !== null ? sprintf(' [%s]', $error->field) : '';
            $io->text(sprintf('  • [%s]%s %s', $error->chainName, $fieldSuffix, $error->message));
        }

        return OrchestrateExitCodeEnum::invalidConfig->value;
    }

    /**
     * Показать план без запуска (--dry-run).
     */
    private function renderDryRun(
        SymfonyStyle $io,
        string $chainName,
        string $task,
        ChainDefinitionVo $chain,
        bool $isDynamic,
        ?string $topic,
        ?string $facilitator,
        ?array $participants,
        ?int $maxRounds,
    ): void {
        $io->section(sprintf('🚀 DRY RUN: Chain "%s" (%s)', $chainName, $isDynamic ? 'dynamic' : 'static'));
        $io->text(sprintf('Task: %s', $task));

        if ($isDynamic) {
            $io->text(sprintf('Facilitator: %s', $facilitator ?? $chain->getFacilitator() ?? 'system_analyst'));
            $io->text(sprintf('Participants: %s', implode(', ', $participants ?? $chain->getParticipants())));
            $io->text(sprintf('Max rounds: %d', $maxRounds ?? $chain->getMaxRounds()));
            if ($topic !== null) {
                $io->text(sprintf('Topic: %s', $topic));
            }
        } else {
            foreach ($chain->getSteps() as $i => $step) {
                if ($step->isQualityGate()) {
                    $io->text(sprintf('  [%d] 🔍 Quality Gate: %s', $i + 1, $step->getLabel()));
                } else {
                    $roleConfig = $chain->getRoleConfig($step->getRole() ?? '');
                    $fallbackRunner = $roleConfig?->getFallback()?->getRunnerName();
                    $fallback = $fallbackRunner !== null
                        ? sprintf(' (fallback: %s)', $fallbackRunner)
                        : '';
                    $io->text(sprintf('  [%d] %s @ %s%s', $i + 1, $step->getRole() ?? '', $step->getRunner(), $fallback));
                }
            }
        }

        $io->note('Запуск не будет выполнен (--dry-run).');
    }

    /**
     * Рендерит результат static-цепочки.
     */
    private function renderStaticResult(SymfonyStyle $io, OrchestrateChainResultDto $result): void
    {
        $total = count($result->stepResults);
        foreach ($result->stepResults as $i => $stepResult) {
            $num = $i + 1;
            $duration = round($stepResult->duration);

            if ($stepResult->role === 'quality_gate') {
                $gateStatus = $stepResult->passed ? '✓' : '✗';
                $io->text(sprintf(
                    '[%d/%d] 🔍 %s: %s (%ds)',
                    $num,
                    $total,
                    $stepResult->label,
                    $gateStatus,
                    $duration,
                ));

                if (!$stepResult->passed) {
                    $io->warning(sprintf(
                        'Quality gate "%s" failed (exit code %d)',
                        $stepResult->label,
                        $stepResult->exitCode,
                    ));
                }
            } else {
                $status = $stepResult->isError ? '✗' : '✓';
                $fallbackSuffix = $stepResult->fallbackRunnerUsed !== null
                    ? sprintf(' → %s', $stepResult->fallbackRunnerUsed)
                    : '';

                $io->text(sprintf(
                    '[%d/%d] %s @ %s%s ... %s (↑%s ↓%s $%.4f, %ds)',
                    $num,
                    $total,
                    $stepResult->role,
                    $stepResult->runner,
                    $fallbackSuffix,
                    $status,
                    $this->formatTokens($stepResult->inputTokens),
                    $this->formatTokens($stepResult->outputTokens),
                    $stepResult->cost,
                    $duration,
                ));

                if ($stepResult->isError) {
                    $io->error(sprintf('Agent error: %s', $stepResult->errorMessage ?? 'Unknown'));
                    break;
                }
            }
        }

        $io->newLine();

        if ($result->budgetExceeded) {
            $io->warning(sprintf(
                '💰 Budget exceeded: spent $%.4f of $%.2f limit. Chain interrupted.',
                $result->totalCost,
                $result->budgetLimit,
            ));
        }

        if ($this->exitCodeResolver->isSuccessfulResult($result, false) && !$result->budgetExceeded) {
            $io->success(sprintf(
                '✅ Chain completed in %ds | Total: ↑%s ↓%s $%.4f',
                round($result->totalTime),
                $this->formatTokens($result->totalInputTokens),
                $this->formatTokens($result->totalOutputTokens),
                $result->totalCost,
            ));
        }
    }

    /**
     * Рендерит результат dynamic-цепочки.
     */
    private function renderDynamicResult(SymfonyStyle $io, OrchestrateChainResultDto $result): void
    {
        if ($result->synthesis !== null) {
            $io->success('✅ Dynamic chain completed with synthesis.');
        } else {
            $io->error('❌ Dynamic chain failed: no synthesis produced.');
        }
    }

    private function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000) {
            return sprintf('%.1fk', $tokens / 1000);
        }

        return (string) $tokens;
    }

    /**
     * Записывает содержимое отчёта в файл или stdout.
     */
    private function writeReport(string $content, ?string $reportFile, SymfonyStyle $io): void
    {
        if ($reportFile !== null && $reportFile !== '') {
            $dir = dirname($reportFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($reportFile, $content);
            $io->note(sprintf('Report written to: %s', $reportFile));
        } else {
            $io->writeln($content);
        }
    }
}
