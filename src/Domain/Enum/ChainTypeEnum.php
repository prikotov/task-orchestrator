<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Enum;

/**
 * Тип цепочки оркестрации.
 *
 * static — фиксированный набор шагов, линейное выполнение.
 * dynamic — фасилитатор решает в рантайме, кому дать слово.
 */
enum ChainTypeEnum: string
{
    case staticType = 'static';
    case dynamicType = 'dynamic';
}
