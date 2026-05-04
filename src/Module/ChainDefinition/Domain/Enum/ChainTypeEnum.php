<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum;

/**
 * Тип цепочки оркестрации.
 *
 * static — фиксированный набор шагов, линейное выполнение.
 * dynamic — фасилитатор решает в рантайме, кому дать слово.
 * conditional — шаги с условным ветвлением (when-expressions).
 */
enum ChainTypeEnum: string
{
    case staticType = 'static';
    case dynamicType = 'dynamic';
    case conditionalType = 'conditional';
}
