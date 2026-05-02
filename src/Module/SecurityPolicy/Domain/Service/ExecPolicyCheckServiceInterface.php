<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecPolicyCheckResultVo;

/**
 * Domain Service: проверка exec policy — матчинг правил против значения.
 *
 * Проверяет команду/runner/tool/модель против набора ExecRule.
 * Возвращает подробный результат (первая violation или ok).
 *
 * Логика deny-first:
 * 1. Фильтрует правила по target
 * 2. Сортирует по priority (descending)
 * 3. Если хотя бы одно deny-правило совпадает → denied
 * 4. Если только allow-правила совпадают → allowed
 * 5. Если ни одно правило не совпало → default deny
 */
interface ExecPolicyCheckServiceInterface
{
    /**
     * Проверяет значение против набора exec rules для указанного target.
     *
     * @param list<ExecRule> $rules набор правил для проверки
     *
     * @return ExecPolicyCheckResultVo подробный результат проверки
     */
    public function check(string $value, RuleTargetEnum $target, array $rules): ExecPolicyCheckResultVo;
}
