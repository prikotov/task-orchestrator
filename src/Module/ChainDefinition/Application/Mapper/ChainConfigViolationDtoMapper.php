<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;

/**
 * Маппинг Domain ChainConfigViolationVo → Application ChainConfigViolationDto.
 */
final readonly class ChainConfigViolationDtoMapper
{
    public function map(ChainConfigViolationVo $violation): ChainConfigViolationDto
    {
        return new ChainConfigViolationDto(
            chainName: $violation->getChainName(),
            field: $violation->getField(),
            message: $violation->getMessage(),
        );
    }

    /**
     * @param list<ChainConfigViolationVo> $violations
     * @return list<ChainConfigViolationDto>
     */
    public function mapList(array $violations): array
    {
        return array_map(
            fn(ChainConfigViolationVo $item): ChainConfigViolationDto => $this->map($item),
            $violations,
        );
    }
}
