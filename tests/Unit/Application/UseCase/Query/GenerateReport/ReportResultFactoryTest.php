<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Query\GenerateReport;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportFormatMapperInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportJsonMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportTextMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\ReportResultFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReportResultFactoryTest extends TestCase
{
    private readonly ReportResultFactory $factory;

    protected function setUp(): void
    {
        /** @var array<string, ReportFormatMapperInterface> $mappers */
        $mappers = [
            'text' => new ReportTextMapper(),
            'json' => new ReportJsonMapper(),
        ];

        $this->factory = new ReportResultFactory($mappers);
    }

    #[Test]
    public function createWithTextFormatReturnsTextReport(): void
    {
        $result = self::createResult();

        $dto = $this->factory->create($result, 'test-chain', 'do stuff', ReportFormatEnum::text);

        self::assertSame('text', $dto->format);
        self::assertStringContainsString('test-chain', $dto->content);
        self::assertStringContainsString('do stuff', $dto->content);
    }

    #[Test]
    public function createWithJsonFormatReturnsJsonReport(): void
    {
        $result = self::createResult();

        $dto = $this->factory->create($result, 'test-chain', 'do stuff', ReportFormatEnum::json);

        self::assertSame('json', $dto->format);
        self::assertJson($dto->content);
    }

    #[Test]
    public function createWithUnknownFormatThrowsException(): void
    {
        $result = self::createResult();

        // Factory without any mappers
        $emptyFactory = new ReportResultFactory([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No mapper registered for report format "text".');

        $emptyFactory->create($result, 'test-chain', 'task', ReportFormatEnum::text);
    }

    private static function createResult(): OrchestrateChainResultDto
    {
        return new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: 'Analysis done',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    duration: 1.5,
                    isError: false,
                ),
            ],
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            totalTime: 1.5,
        );
    }
}
