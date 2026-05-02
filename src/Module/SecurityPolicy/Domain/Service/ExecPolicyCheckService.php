<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service;

use Override;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecPolicyCheckResultVo;

/**
 * Domain Service: проверка exec policy — матчинг правил против значения.
 *
 * Deny-first логика:
 * 1. Фильтрует правила по target
 * 2. Сортирует по priority (descending — более высокий приоритет первым)
 * 3. Если хотя бы одно deny-правило совпадает → denied
 * 4. Если только allow-правила совпадают → allowed
 * 5. Если ни одно правило не совпало → default deny
 */
final readonly class ExecPolicyCheckService implements ExecPolicyCheckServiceInterface
{
    #[Override]
    public function check(string $value, RuleTargetEnum $target, array $rules): ExecPolicyCheckResultVo
    {
        // 1. Фильтруем по target
        $targetRules = array_filter($rules, static fn (ExecRule $rule): bool => $rule->targets($target));

        // 2. Сортируем по priority (descending)
        $sortedRules = $this->sortByPriorityDesc($targetRules);

        // 3. Находим все совпадающие правила
        $matchedRules = [];
        $deniedRule = null;

        foreach ($sortedRules as $rule) {
            if (!$rule->matches($value)) {
                continue;
            }

            $matchedRules[] = $rule;

            // Deny-first: первое совпадение deny → violation
            if ($rule->isDeny() && $deniedRule === null) {
                $deniedRule = $rule;
            }
        }

        // 4. Если нашли deny → denied
        if ($deniedRule !== null) {
            return ExecPolicyCheckResultVo::createDenied(
                checkedValue: $value,
                target: $target,
                violatedRule: $deniedRule,
                matchedRules: $matchedRules,
            );
        }

        // 5. Если есть хотя бы один allow → allowed
        if ($matchedRules !== []) {
            return ExecPolicyCheckResultVo::createAllowed(
                checkedValue: $value,
                target: $target,
                matchedRules: $matchedRules,
            );
        }

        // 6. Нет совпадений → default deny
        return ExecPolicyCheckResultVo::createDefaultDenied(
            checkedValue: $value,
            target: $target,
        );
    }

    /**
     * Сортирует правила по priority по убыванию (высший приоритет первым).
     *
     * @param array<array-key, ExecRule> $rules
     * @return list<ExecRule>
     */
    private function sortByPriorityDesc(array $rules): array
    {
        usort($rules, static fn (ExecRule $a, ExecRule $b): int => $b->getPriority() <=> $a->getPriority());

        return $rules;
    }
}
