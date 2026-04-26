<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainConfigViolationVo;

/**
 * Маппинг Domain ChainConfigViolationVo → Application ChainConfigViolationDto.
 */
final readonly class ChainConfigViolationDtoMapper
{
    public function map(ChainConfigViolationVo $vo): ChainConfigViolationDto
    {
        return new ChainConfigViolationDto(
            chainName: $vo->getChainName(),
            field: $vo->getField(),
            message: $vo->getMessage(),
        );
    }

    /**
     * @param list<ChainConfigViolationVo> $violations
     * @return list<ChainConfigViolationDto>
     */
    public function mapList(array $violations): array
    {
        return array_map(
            fn(ChainConfigViolationVo $v): ChainConfigViolationDto => $this->map($v),
            $violations,
        );
    }
}
