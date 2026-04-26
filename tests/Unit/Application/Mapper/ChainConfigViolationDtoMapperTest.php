<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ChainConfigViolationDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainConfigViolationVo;

#[CoversClass(ChainConfigViolationDtoMapper::class)]
final class ChainConfigViolationDtoMapperTest extends TestCase
{
    private ChainConfigViolationDtoMapper $mapper;

    #[Override]
    protected function setUp(): void
    {
        $this->mapper = new ChainConfigViolationDtoMapper();
    }

    #[Test]
    public function mapSingleViolation(): void
    {
        $vo = new ChainConfigViolationVo('mychain', 'steps', 'No steps defined.');

        $dto = $this->mapper->map($vo);

        self::assertSame('mychain', $dto->chainName);
        self::assertSame('steps', $dto->field);
        self::assertSame('No steps defined.', $dto->message);
    }

    #[Test]
    public function mapListMapsAll(): void
    {
        $violations = [
            new ChainConfigViolationVo('chain1', 'field1', 'Error 1'),
            new ChainConfigViolationVo('chain2', 'field2', 'Error 2'),
        ];

        $result = $this->mapper->mapList($violations);

        self::assertCount(2, $result);
        self::assertSame('chain1', $result[0]->chainName);
        self::assertSame('chain2', $result[1]->chainName);
    }

    #[Test]
    public function mapListEmptyReturnsEmpty(): void
    {
        $result = $this->mapper->mapList([]);

        self::assertSame([], $result);
    }
}
