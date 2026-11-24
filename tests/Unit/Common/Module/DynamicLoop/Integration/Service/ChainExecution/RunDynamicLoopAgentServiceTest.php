<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\DynamicLoop\Integration\Service\ChainExecution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Agent\RunAgent\RunAgentQueryHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Prompt\GetPromptFilePath\GetPromptFilePathQuery;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionWriterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Shared\DynamicLoopPromptFormatterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorFinalizeRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\ParticipantRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution\RunDynamicLoopAgentService;

/**
 * Модульные тесты маппинга VO → форматтер/запрос в RunDynamicLoopAgentService.
 *
 * Интеграционные тесты dynamic-цепочек подменяют RunDynamicLoopServiceInterface стабом,
 * поэтому эта Integration-реализация ранее не покрывалась. Здесь на стабах зависимостей
 * закреплено для runFacilitator/runParticipant/runFacilitatorFinalize:
 * - порядок аргументов formatter->buildFacilitatorContext / buildParticipantUserPrompt /
 *   buildFinalizeContext (в частности, различение facilitatorStartPrompt и
 *   facilitatorContinuePrompt, facilitatorSummary, responseFilesList, taskAddendum);
 * - поля DynamicLoopRunRequestVo и маппинг в ChainRunRequestVo (включая retry policy);
 * - запись prompt-файлов сессии и формирование DynamicLoopTurnResultVo.
 */
#[CoversClass(RunDynamicLoopAgentService::class)]
final class RunDynamicLoopAgentServiceTest extends TestCase
{
    private const TOPIC = 'Test topic';

    // ─── runFacilitator ───────────────────────────────────────────────

    #[Test]
    public function runFacilitatorMapsVoTailToFormatterContextAndRunRequest(): void
    {
        $facilitatorContextArgs = null;
        $invocationArgs = null;
        $writtenPrompts = [];
        $parsedTexts = [];
        $agentRunnerCalls = [];
        $facilitatorResponse = FacilitatorResponseVo::createNextRole('architect', 'challenge');
        $service = $this->createService(
            $this->createAgentRunner(
                ChainRunResultVo::createSuccess(
                    'facilitator-json',
                    inputTokens: 10,
                    outputTokens: 20,
                    cost: 0.5,
                    model: 'test-model',
                    provider: 'test-provider',
                    turns: 2,
                ),
                $agentRunnerCalls,
            ),
            $this->createSessionWriter($writtenPrompts),
            $this->createResponseParser($parsedTexts, $facilitatorResponse),
            $this->createFormatter(
                facilitatorContextArgs: $facilitatorContextArgs,
                contextResult: 'formatted-facilitator-context',
                invocationArgs: $invocationArgs,
            ),
        );

        $result = $service->runFacilitator(
            step: 1,
            round: 1,
            facilitatorRole: 'facilitator',
            topic: self::TOPIC,
            runContext: $this->createFacilitatorContext(),
        );

        // Хвост VO уходит в форматтер в точном порядке: start/continue не перепутаны,
        // summary, список файлов и дополнение Лида переданы как есть.
        self::assertSame(
            [
                'facilitator-start-prompt',
                'facilitator-continue-prompt',
                self::TOPIC,
                'facilitator-summary',
                'response-files-list',
                'task-addendum',
            ],
            $facilitatorContextArgs,
        );

        // Prompt-файлы сессии: сначала system (brainstorm-промпт), затем append.
        self::assertCount(2, $writtenPrompts);
        self::assertSame(1, $writtenPrompts[0]['step']);
        self::assertSame(1, $writtenPrompts[0]['round']);
        self::assertSame('facilitator', $writtenPrompts[0]['role']);
        self::assertSame('brainstorm-system-prompt', $writtenPrompts[0]['content']);
        self::assertSame('_1_system.md', $writtenPrompts[0]['suffix']);
        self::assertSame('facilitator-append-prompt', $writtenPrompts[1]['content']);
        self::assertSame('_2_append.md', $writtenPrompts[1]['suffix']);

        // Поля DynamicLoopRunRequestVo (захвачены через buildAgentInvocation).
        self::assertNotNull($invocationArgs);
        $runRequest = $invocationArgs['request'];
        self::assertInstanceOf(DynamicLoopRunRequestVo::class, $runRequest);
        self::assertSame('facilitator', $runRequest->getRole());
        self::assertSame(self::TOPIC, $runRequest->getTask());
        self::assertSame('/session/1_1_facilitator_1_system.md', $runRequest->getSystemPrompt());
        self::assertSame('formatted-facilitator-context', $runRequest->getPreviousContext());
        self::assertNull($runRequest->getModel());
        self::assertNull($runRequest->getTools());
        self::assertSame('/tmp/agent-workdir', $runRequest->getWorkingDir());
        self::assertSame(120, $runRequest->getTimeout());
        self::assertSame(['pi', '--model', 'test'], $runRequest->getCommand());
        self::assertSame(
            ['--append-system-prompt', '/session/1_1_facilitator_2_append.md'],
            $runRequest->getRunnerArgs(),
        );
        self::assertNull($runRequest->getRunnerName());
        self::assertFalse($runRequest->hasNoContextFiles());
        self::assertNull($runRequest->getRetryPolicy());
        self::assertSame('prompts/1_1_facilitator.md', $invocationArgs['userPromptFile']);

        // Маппинг DynamicLoopRunRequestVo → ChainRunRequestVo; retry policy выключена (null).
        self::assertCount(1, $agentRunnerCalls);
        $chainRequest = $agentRunnerCalls[0]['request'];
        self::assertInstanceOf(ChainRunRequestVo::class, $chainRequest);
        self::assertSame('facilitator', $chainRequest->getRole());
        self::assertSame(self::TOPIC, $chainRequest->getTask());
        self::assertSame('/session/1_1_facilitator_1_system.md', $chainRequest->getSystemPrompt());
        self::assertSame('formatted-facilitator-context', $chainRequest->getPreviousContext());
        self::assertNull($chainRequest->getModel());
        self::assertNull($chainRequest->getTools());
        self::assertSame('/tmp/agent-workdir', $chainRequest->getWorkingDir());
        self::assertSame(120, $chainRequest->getTimeout());
        self::assertSame(50000, $chainRequest->getMaxContextLength());
        self::assertSame(['pi', '--model', 'test'], $chainRequest->getCommand());
        self::assertSame(
            ['--append-system-prompt', '/session/1_1_facilitator_2_append.md'],
            $chainRequest->getRunnerArgs(),
        );
        self::assertNull($chainRequest->getRunnerName());
        self::assertFalse($chainRequest->hasNoContextFiles());
        self::assertNull($agentRunnerCalls[0]['retryPolicy']);

        // Ответ runner'а передаётся парсеру фасилитатора.
        self::assertSame(['facilitator-json'], $parsedTexts);

        // Возврат: кортеж [DynamicLoopTurnResultVo, FacilitatorResponseVo].
        self::assertCount(2, $result);
        $turnResult = $result[0];
        self::assertInstanceOf(DynamicLoopTurnResultVo::class, $turnResult);
        self::assertSame('facilitator-json', $turnResult->agentResult->getOutputText());
        self::assertSame(10, $turnResult->agentResult->getInputTokens());
        self::assertSame(20, $turnResult->agentResult->getOutputTokens());
        self::assertSame(0.5, $turnResult->agentResult->getCost());
        self::assertSame('test-model', $turnResult->agentResult->getModel());
        self::assertSame('test-provider', $turnResult->agentResult->getProvider());
        self::assertSame(2, $turnResult->agentResult->getTurns());
        self::assertSame('formatted-facilitator-context', $turnResult->userPrompt);
        self::assertSame('brainstorm-system-prompt', $turnResult->systemPrompt);
        self::assertSame('invocation-command', $turnResult->invocation);
        self::assertSame($facilitatorResponse, $result[1]);
    }

    #[Test]
    public function runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy(): void
    {
        $agentRunnerCalls = [];
        $service = $this->createService(
            $this->createAgentRunner(ChainRunResultVo::createSuccess('ok'), $agentRunnerCalls),
            $this->createSessionWriter(),
            $this->createResponseParser(),
            $this->createFormatter(),
        );

        $retryPolicy = new DynamicLoopRetryPolicyVo(
            maxRetries: 2,
            initialDelayMs: 500,
            maxDelayMs: 9000,
            multiplier: 3.0,
        );
        $service->runFacilitator(
            1,
            1,
            'facilitator',
            self::TOPIC,
            $this->createFacilitatorContext(retryPolicy: $retryPolicy),
        );

        $mapped = $agentRunnerCalls[0]['retryPolicy'];
        self::assertInstanceOf(ExecutionRetryPolicyVo::class, $mapped);
        self::assertSame(2, $mapped->getMaxRetries());
        self::assertSame(500, $mapped->getInitialDelayMs());
        self::assertSame(9000, $mapped->getMaxDelayMs());
        self::assertSame(3.0, $mapped->getMultiplier());
        self::assertTrue($mapped->isEnabled());
    }

    #[Test]
    public function runFacilitatorMapsAgentRunnerErrorToErrorRunResult(): void
    {
        $agentRunnerCalls = [];
        $parsedTexts = [];
        $service = $this->createService(
            $this->createAgentRunner(
                ChainRunResultVo::createError('agent boom', exitCode: 42, timedOut: true),
                $agentRunnerCalls,
            ),
            $this->createSessionWriter(),
            $this->createResponseParser($parsedTexts),
            $this->createFormatter(contextResult: 'formatted-facilitator-context'),
        );

        $result = $service->runFacilitator(
            1,
            1,
            'facilitator',
            self::TOPIC,
            $this->createFacilitatorContext(),
        );

        $turnResult = $result[0];
        self::assertInstanceOf(DynamicLoopTurnResultVo::class, $turnResult);
        self::assertTrue($turnResult->agentResult->isError());
        self::assertSame('agent boom', $turnResult->agentResult->getErrorMessage());
        self::assertSame(42, $turnResult->agentResult->getExitCode());
        self::assertTrue($turnResult->agentResult->isTimedOut());
        // При ошибке ответ runner'а пуст — парсер вызывается с пустой строкой
        // (закрепляем текущее поведение маппинга).
        self::assertSame([''], $parsedTexts);
        // Метаданные хода заполняются и при ошибке.
        self::assertSame('formatted-facilitator-context', $turnResult->userPrompt);
        self::assertSame('brainstorm-system-prompt', $turnResult->systemPrompt);
        self::assertSame('invocation-command', $turnResult->invocation);
    }

    // ─── runParticipant ───────────────────────────────────────────────

    #[Test]
    public function runParticipantMapsVoTailToFormatterPromptAndRunRequest(): void
    {
        $participantUserPromptArgs = null;
        $invocationArgs = null;
        $writtenPrompts = [];
        $capturedQueries = [];
        $agentRunnerCalls = [];
        $service = $this->createService(
            $this->createAgentRunner(
                ChainRunResultVo::createSuccess('participant-output', inputTokens: 5, outputTokens: 7),
                $agentRunnerCalls,
            ),
            $this->createSessionWriter($writtenPrompts),
            $this->createResponseParser(),
            $this->createFormatter(
                participantUserPromptArgs: $participantUserPromptArgs,
                contextResult: 'formatted-participant-context',
                invocationArgs: $invocationArgs,
            ),
            $this->createQueryBus('/resolved/architect-role.md', $capturedQueries),
        );

        $context = new ParticipantRunContextVo(
            brainstormSystemPrompt: 'participant-brainstorm-system-prompt',
            participantAppendPrompt: 'append for %s role file',
            participantUserPrompt: 'participant-user-prompt',
            workingDir: '/tmp/participant-workdir',
            responseFilesList: 'participant-response-files',
            timeout: 60,
            command: ['pi'],
            hasPreviousResponses: false,
            challenge: 'facilitator-challenge',
        );

        $turnResult = $service->runParticipant(2, 1, 'architect', self::TOPIC, $context);

        // Путь к файлу роли резолвится через QueryBus по имени роли.
        self::assertCount(1, $capturedQueries);
        self::assertInstanceOf(GetPromptFilePathQuery::class, $capturedQueries[0]);
        self::assertSame('architect', $capturedQueries[0]->role);

        // Хвост VO уходит в форматтер в точном порядке.
        self::assertSame(
            [
                'participant-user-prompt',
                self::TOPIC,
                'participant-response-files',
                false,
                'facilitator-challenge',
            ],
            $participantUserPromptArgs,
        );

        // Prompt-файлы сессии: system — brainstorm-промпт, append — с подставленным путём роли.
        self::assertCount(2, $writtenPrompts);
        self::assertSame('participant-brainstorm-system-prompt', $writtenPrompts[0]['content']);
        self::assertSame('_1_system.md', $writtenPrompts[0]['suffix']);
        self::assertSame('append for /resolved/architect-role.md role file', $writtenPrompts[1]['content']);
        self::assertSame('_2_append.md', $writtenPrompts[1]['suffix']);

        // Поля DynamicLoopRunRequestVo (захвачены через buildAgentInvocation).
        self::assertNotNull($invocationArgs);
        $runRequest = $invocationArgs['request'];
        self::assertInstanceOf(DynamicLoopRunRequestVo::class, $runRequest);
        self::assertSame('architect', $runRequest->getRole());
        self::assertSame(self::TOPIC, $runRequest->getTask());
        self::assertSame('/session/2_1_architect_1_system.md', $runRequest->getSystemPrompt());
        self::assertSame('formatted-participant-context', $runRequest->getPreviousContext());
        self::assertNull($runRequest->getModel());
        self::assertNull($runRequest->getTools());
        self::assertSame('/tmp/participant-workdir', $runRequest->getWorkingDir());
        self::assertSame(60, $runRequest->getTimeout());
        self::assertSame(['pi'], $runRequest->getCommand());
        self::assertSame(
            ['--append-system-prompt', '/session/2_1_architect_2_append.md'],
            $runRequest->getRunnerArgs(),
        );
        self::assertNull($runRequest->getRetryPolicy());
        self::assertSame('prompts/2_1_architect.md', $invocationArgs['userPromptFile']);

        // Маппинг в ChainRunRequestVo; retry policy выключена (null).
        self::assertCount(1, $agentRunnerCalls);
        self::assertSame('architect', $agentRunnerCalls[0]['request']->getRole());
        self::assertNull($agentRunnerCalls[0]['retryPolicy']);

        // Результат хода.
        self::assertInstanceOf(DynamicLoopTurnResultVo::class, $turnResult);
        self::assertSame('participant-output', $turnResult->agentResult->getOutputText());
        self::assertSame(5, $turnResult->agentResult->getInputTokens());
        self::assertSame(7, $turnResult->agentResult->getOutputTokens());
        self::assertSame('formatted-participant-context', $turnResult->userPrompt);
        self::assertSame('participant-brainstorm-system-prompt', $turnResult->systemPrompt);
        self::assertSame('invocation-command', $turnResult->invocation);
    }

    #[Test]
    public function runParticipantPrefersVoPromptFileOverResolvedRolePath(): void
    {
        $writtenPrompts = [];
        $service = $this->createService(
            $this->createAgentRunner(ChainRunResultVo::createSuccess('ok')),
            $this->createSessionWriter($writtenPrompts),
            $this->createResponseParser(),
            $this->createFormatter(),
            $this->createQueryBus('/resolved/architect-role.md'),
        );

        $context = new ParticipantRunContextVo(
            brainstormSystemPrompt: 'sys',
            participantAppendPrompt: 'append %s',
            participantUserPrompt: 'user prompt',
            workingDir: null,
            responseFilesList: '',
            timeout: 30,
            promptFile: '/custom/role.md',
        );

        $service->runParticipant(2, 1, 'architect', self::TOPIC, $context);

        // Путь из VO вытесняет путь, отрезолвенный через QueryBus.
        self::assertSame('append /custom/role.md', $writtenPrompts[1]['content']);
    }

    // ─── runFacilitatorFinalize ───────────────────────────────────────

    #[Test]
    public function runFacilitatorFinalizeMapsVoTailToFormatterContextAndRunRequest(): void
    {
        $finalizeContextArgs = null;
        $invocationArgs = null;
        $writtenPrompts = [];
        $agentRunnerCalls = [];
        $service = $this->createService(
            $this->createAgentRunner(ChainRunResultVo::createSuccess('finalize-output'), $agentRunnerCalls),
            $this->createSessionWriter($writtenPrompts),
            $this->createResponseParser(),
            $this->createFormatter(
                finalizeContextArgs: $finalizeContextArgs,
                contextResult: 'formatted-finalize-context',
                invocationArgs: $invocationArgs,
            ),
        );

        $context = new FacilitatorFinalizeRunContextVo(
            brainstormSystemPrompt: 'finalize-brainstorm-system-prompt',
            facilitatorAppendPrompt: 'finalize-append-prompt',
            facilitatorFinalizePrompt: 'facilitator-finalize-prompt',
            workingDir: null,
            responseFilesList: 'finalize-response-files',
            timeout: 90,
            command: ['pi', '--model', 'finalize'],
        );

        $turnResult = $service->runFacilitatorFinalize(1, 3, 'facilitator', self::TOPIC, $context);

        // Хвост VO уходит в форматтер в точном порядке.
        self::assertSame(
            ['facilitator-finalize-prompt', self::TOPIC, 'finalize-response-files'],
            $finalizeContextArgs,
        );

        // Prompt-файлы сессии: system — brainstorm-промпт, append — facilitatorAppendPrompt.
        self::assertCount(2, $writtenPrompts);
        self::assertSame(1, $writtenPrompts[0]['step']);
        self::assertSame(3, $writtenPrompts[0]['round']);
        self::assertSame('finalize-brainstorm-system-prompt', $writtenPrompts[0]['content']);
        self::assertSame('_1_system.md', $writtenPrompts[0]['suffix']);
        self::assertSame('finalize-append-prompt', $writtenPrompts[1]['content']);
        self::assertSame('_2_append.md', $writtenPrompts[1]['suffix']);

        // Поля DynamicLoopRunRequestVo (захвачены через buildAgentInvocation).
        self::assertNotNull($invocationArgs);
        $runRequest = $invocationArgs['request'];
        self::assertInstanceOf(DynamicLoopRunRequestVo::class, $runRequest);
        self::assertSame('facilitator', $runRequest->getRole());
        self::assertSame(self::TOPIC, $runRequest->getTask());
        self::assertSame('/session/1_3_facilitator_1_system.md', $runRequest->getSystemPrompt());
        self::assertSame('formatted-finalize-context', $runRequest->getPreviousContext());
        self::assertNull($runRequest->getWorkingDir());
        self::assertSame(90, $runRequest->getTimeout());
        self::assertSame(['pi', '--model', 'finalize'], $runRequest->getCommand());
        self::assertSame(
            ['--append-system-prompt', '/session/1_3_facilitator_2_append.md'],
            $runRequest->getRunnerArgs(),
        );
        self::assertSame('prompts/1_3_facilitator.md', $invocationArgs['userPromptFile']);

        // Результат хода.
        self::assertInstanceOf(DynamicLoopTurnResultVo::class, $turnResult);
        self::assertSame('finalize-output', $turnResult->agentResult->getOutputText());
        self::assertSame('formatted-finalize-context', $turnResult->userPrompt);
        self::assertSame('finalize-brainstorm-system-prompt', $turnResult->systemPrompt);
        self::assertSame('invocation-command', $turnResult->invocation);
    }

    // ─── Фабрики стабов ───────────────────────────────────────────────

    private function createService(
        RunAgentQueryHandler $agentRunner,
        DynamicLoopSessionWriterInterface $sessionWriter,
        FacilitatorResponseParserInterface $responseParser,
        DynamicLoopPromptFormatterInterface $formatter,
        ?QueryBusComponentInterface $queryBus = null,
    ): RunDynamicLoopAgentService {
        return new RunDynamicLoopAgentService(
            $agentRunner,
            $sessionWriter,
            $responseParser,
            $queryBus ?? $this->createStub(QueryBusComponentInterface::class),
            $formatter,
        );
    }

    private function createFacilitatorContext(
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
        ?string $taskAddendum = 'task-addendum',
    ): FacilitatorRunContextVo {
        return new FacilitatorRunContextVo(
            brainstormSystemPrompt: 'brainstorm-system-prompt',
            facilitatorAppendPrompt: 'facilitator-append-prompt',
            facilitatorStartPrompt: 'facilitator-start-prompt',
            facilitatorContinuePrompt: 'facilitator-continue-prompt',
            workingDir: '/tmp/agent-workdir',
            facilitatorSummary: 'facilitator-summary',
            responseFilesList: 'response-files-list',
            timeout: 120,
            command: ['pi', '--model', 'test'],
            retryPolicy: $retryPolicy,
            taskAddendum: $taskAddendum,
        );
    }

    /**
     * Стаб agent runner'а: запоминает запрос и retry-политику, возвращает заданный результат.
     *
     * @param list<array{request: ChainRunRequestVo, retryPolicy: ?ExecutionRetryPolicyVo}> $captured
     */
    private function createAgentRunner(ChainRunResultVo $result, array &$captured = []): RunAgentQueryHandler
    {
        $runner = $this->createStub(RunAgentQueryHandler::class);
        $runner->method('run')->willReturnCallback(
            static function (ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy) use ($result, &$captured): ChainRunResultVo {
                $captured[] = ['request' => $request, 'retryPolicy' => $retryPolicy];

                return $result;
            },
        );

        return $runner;
    }

    /**
     * Стаб записи сессии: запоминает prompt-файлы, путь детерминированный.
     *
     * @param list<array{step: int, round: int, role: string, content: string, suffix: string, filePath: string}> $captured
     */
    private function createSessionWriter(array &$captured = []): DynamicLoopSessionWriterInterface
    {
        $writer = $this->createStub(DynamicLoopSessionWriterInterface::class);
        $writer->method('writePromptFile')->willReturnCallback(
            static function (int $step, int $round, string $role, string $content, string $suffix) use (&$captured): string {
                $filePath = sprintf('/session/%d_%d_%s%s', $step, $round, $role, $suffix);
                $captured[] = [
                    'step' => $step,
                    'round' => $round,
                    'role' => $role,
                    'content' => $content,
                    'suffix' => $suffix,
                    'filePath' => $filePath,
                ];

                return $filePath;
            },
        );

        return $writer;
    }

    /**
     * Стаб парсера ответа фасилитатора: запоминает входной текст, возвращает заданный VO.
     *
     * @param list<string> $captured
     */
    private function createResponseParser(array &$captured = [], ?FacilitatorResponseVo $response = null): FacilitatorResponseParserInterface
    {
        $response ??= FacilitatorResponseVo::createNextRole('architect');
        $parser = $this->createStub(FacilitatorResponseParserInterface::class);
        $parser->method('parse')->willReturnCallback(
            static function (string $llmText) use (&$captured, $response): FacilitatorResponseVo {
                $captured[] = $llmText;

                return $response;
            },
        );

        return $parser;
    }

    /**
     * Стаб шины запросов: запоминает запросы, возвращает заданный путь к файлу роли.
     *
     * @param list<object> $captured
     */
    private function createQueryBus(string $resolvedRolePath, array &$captured = []): QueryBusComponentInterface
    {
        $queryBus = $this->createStub(QueryBusComponentInterface::class);
        $queryBus->method('query')->willReturnCallback(
            static function (object $query) use ($resolvedRolePath, &$captured): string {
                $captured[] = $query;

                return $resolvedRolePath;
            },
        );

        return $queryBus;
    }

    /**
     * Стаб форматтера: запоминает аргументы сборки контекстов и артефактов запуска.
     *
     * @param list<string>|null $facilitatorContextArgs
     * @param list<mixed>|null $participantUserPromptArgs
     * @param list<string>|null $finalizeContextArgs
     * @param array{request: DynamicLoopRunRequestVo, userPromptFile: string}|null $invocationArgs
     */
    private function createFormatter(
        ?array &$facilitatorContextArgs = null,
        ?array &$participantUserPromptArgs = null,
        ?array &$finalizeContextArgs = null,
        ?array &$invocationArgs = null,
        string $contextResult = 'formatted-context',
    ): DynamicLoopPromptFormatterInterface {
        $formatter = $this->createStub(DynamicLoopPromptFormatterInterface::class);

        $formatter->method('buildFacilitatorContext')->willReturnCallback(
            static function (
                string $startPrompt,
                string $continuePrompt,
                string $topic,
                string $facilitatorSummary,
                string $responseFilesList,
                ?string $taskAddendum,
            ) use (&$facilitatorContextArgs, $contextResult): string {
                $facilitatorContextArgs = [$startPrompt, $continuePrompt, $topic, $facilitatorSummary, $responseFilesList, $taskAddendum];

                return $contextResult;
            },
        );

        $formatter->method('buildParticipantUserPrompt')->willReturnCallback(
            static function (
                string $userPromptTemplate,
                string $topic,
                string $responseFilesList,
                bool $hasPreviousResponses,
                ?string $challenge,
            ) use (&$participantUserPromptArgs, $contextResult): string {
                $participantUserPromptArgs = [$userPromptTemplate, $topic, $responseFilesList, $hasPreviousResponses, $challenge];

                return $contextResult;
            },
        );

        $formatter->method('buildFinalizeContext')->willReturnCallback(
            static function (
                string $finalizePrompt,
                string $topic,
                string $responseFilesList,
            ) use (&$finalizeContextArgs, $contextResult): string {
                $finalizeContextArgs = [$finalizePrompt, $topic, $responseFilesList];

                return $contextResult;
            },
        );

        $formatter->method('buildUserPromptFileName')->willReturnCallback(
            static function (int $step, int $round, string $role): string {
                return sprintf('prompts/%d_%d_%s.md', $step, $round, $role);
            },
        );

        $formatter->method('buildAgentInvocation')->willReturnCallback(
            static function (DynamicLoopRunRequestVo $request, string $userPromptFile) use (&$invocationArgs): string {
                $invocationArgs = ['request' => $request, 'userPromptFile' => $userPromptFile];

                return 'invocation-command';
            },
        );

        return $formatter;
    }
}
